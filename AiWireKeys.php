<?php namespace ProcessWire;

/**
 * AiWireKeys — encrypted storage for provider API keys in a dedicated table.
 *
 * Provider keys are OUTBOUND (sent to the provider on every request), so they
 * must be recoverable — they're encrypted, not hashed. Stored in `aiwire_keys`
 * (separate from the module config), with the value encrypted via libsodium
 * secretbox. The encryption key is DERIVED from a secret kept in config.php
 * (outside the DB), so a database dump only ever contains ciphertext.
 *
 * Secret source (first non-empty): $config->aiwireSecret → tableSalt → userAuthSalt.
 * NOTE: do not change that salt after keys are stored — they'd need re-entering.
 */
class AiWireKeys extends Wire {

	const TABLE   = 'aiwire_keys';
	const CONTEXT = 'AiWireKeys.v1'; // domain separation + versioning for the derived key

	/** Create the table if missing. */
	public function ensureTable(): void {
		$this->wire('database')->exec(
			"CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
				`id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`provider`     VARCHAR(64)  NOT NULL,
				`label`        VARCHAR(255) NOT NULL DEFAULT '',
				`key_enc`      TEXT         NOT NULL,
				`model`        VARCHAR(255) NOT NULL DEFAULT '',
				`custom_model` VARCHAR(255) NOT NULL DEFAULT '',
				`enabled`      TINYINT(1)   NOT NULL DEFAULT 1,
				`status`       VARCHAR(32)  NOT NULL DEFAULT '',
				`sort`         INT          NOT NULL DEFAULT 0,
				`created`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`modified`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `provider` (`provider`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		// Forward-compatible: add columns introduced after a table already existed.
		$this->ensureColumn('custom_model', "VARCHAR(255) NOT NULL DEFAULT '' AFTER `model`");
	}

	/** Add a column if it's missing (idempotent schema guard for upgrades). */
	protected function ensureColumn(string $column, string $definition): void {
		try {
			$db  = $this->wire('database');
			$chk = $db->prepare("SHOW COLUMNS FROM `" . self::TABLE . "` LIKE ?");
			$chk->execute([$column]);
			if ($chk->fetch()) return;
			$db->exec("ALTER TABLE `" . self::TABLE . "` ADD COLUMN `{$column}` {$definition}");
		} catch (\Throwable $e) {
			$this->wire('log')->error('aiwire keys ensureColumn: ' . $e->getMessage());
		}
	}

	public function dropTable(): void {
		$this->wire('database')->exec("DROP TABLE IF EXISTS `" . self::TABLE . "`");
	}

	/** 32-byte encryption key derived from a config.php secret (never the DB). */
	protected function secretKey(): string {
		$cfg = $this->wire('config');
		$base = (string)($cfg->aiwireSecret ?: ($cfg->tableSalt ?: $cfg->userAuthSalt));
		return sodium_crypto_generichash(self::CONTEXT . '|' . $base, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
	}

	/** Encrypt a key → base64(nonce . ciphertext). '' stays ''. */
	public function encrypt(string $plain): string {
		if ($plain === '') return '';
		$nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = sodium_crypto_secretbox($plain, $nonce, $this->secretKey());
		return base64_encode($nonce . $cipher);
	}

	/** Decrypt a stored value, or '' on failure (wrong/changed secret, corruption). */
	public function decrypt(string $stored): string {
		if ($stored === '') return '';
		$raw = base64_decode($stored, true);
		if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
		$nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->secretKey());
		return $plain === false ? '' : $plain;
	}

	/* ----------------------------------------------------------------- CRUD */

	/** Add a key for a provider; returns the new row id (0 on failure). */
	public function add(string $provider, string $key, array $meta = []): int {
		if ($provider === '' || $key === '') return 0;
		$this->ensureTable();
		try {
			$stmt = $this->wire('database')->prepare(
				"INSERT INTO `" . self::TABLE . "` (`provider`,`label`,`key_enc`,`model`,`custom_model`,`enabled`,`status`,`sort`)
				 VALUES (:p,:l,:k,:m,:cm,:e,:s,:so)"
			);
			$stmt->execute([
				':p'  => $provider,
				':l'  => (string)($meta['label'] ?? ''),
				':k'  => $this->encrypt($key),
				':m'  => (string)($meta['model'] ?? ''),
				':cm' => (string)($meta['custom_model'] ?? ''),
				':e'  => !empty($meta['enabled']) || !array_key_exists('enabled', $meta) ? 1 : 0,
				':s'  => (string)($meta['status'] ?? ''),
				':so' => (int)($meta['sort'] ?? 0),
			]);
			return (int)$this->wire('database')->lastInsertId();
		} catch (\Throwable $e) {
			$this->wire('log')->error('aiwire keys add: ' . $e->getMessage());
			return 0;
		}
	}

	/**
	 * Replace ALL keys for a provider with the given entries (encrypting each).
	 * Entries are assoc arrays: key, label, model, custom_model, enabled, status.
	 * Order is preserved via `sort`. Returns number of keys written.
	 */
	public function replaceProvider(string $provider, array $entries): int {
		if ($provider === '') return 0;
		$this->ensureTable();
		$this->deleteProvider($provider);
		$n = 0;
		foreach (array_values($entries) as $i => $e) {
			if (!is_array($e)) continue;
			$key = trim((string)($e['key'] ?? ''));
			if ($key === '') continue; // never store an empty key row
			$this->add($provider, $key, [
				'label'        => $e['label'] ?? '',
				'model'        => $e['model'] ?? '',
				'custom_model' => $e['custom_model'] ?? '',
				'enabled'      => !empty($e['enabled']),
				'status'       => $e['status'] ?? 'unknown',
				'sort'         => $i,
			]);
			$n++;
		}
		return $n;
	}

	public function deleteProvider(string $provider): void {
		try { $this->wire('database')->prepare("DELETE FROM `" . self::TABLE . "` WHERE `provider` = ?")->execute([$provider]); }
		catch (\Throwable $e) {}
	}

	/**
	 * Keys for a provider, decrypted, in the shape the rest of AiWire expects:
	 * [['key'=>, 'label'=>, 'model'=>, 'enabled'=>, 'status'=>], ...]
	 */
	public function getProviderKeys(string $provider): array {
		try {
			$stmt = $this->wire('database')->prepare(
				"SELECT * FROM `" . self::TABLE . "` WHERE `provider` = ? ORDER BY `sort`, `id`"
			);
			$stmt->execute([$provider]);
			$out = [];
			foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$out[] = [
					'key'          => $this->decrypt((string)$row['key_enc']),
					'label'        => (string)$row['label'],
					'model'        => (string)$row['model'],
					'custom_model' => (string)($row['custom_model'] ?? ''),
					'enabled'      => (bool)$row['enabled'],
					'status'       => (string)$row['status'],
					'id'           => (int)$row['id'],
				];
			}
			return $out;
		} catch (\Throwable $e) {
			return [];
		}
	}

	/** True if any key row exists (used to decide table vs legacy-config source). */
	public function hasAny(): bool {
		try {
			return (int)$this->wire('database')->query("SELECT COUNT(*) FROM `" . self::TABLE . "`")->fetchColumn() > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	public function delete(int $id): void {
		try { $this->wire('database')->prepare("DELETE FROM `" . self::TABLE . "` WHERE `id` = ?")->execute([$id]); }
		catch (\Throwable $e) {}
	}
}
