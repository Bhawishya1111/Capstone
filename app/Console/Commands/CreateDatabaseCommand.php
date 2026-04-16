<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use PDO;
use PDOException;

/**
 * Creates the database defined in DB_DATABASE before you run migrations.
 * Requires a MySQL user with CREATE privilege (often root for local dev).
 */
class CreateDatabaseCommand extends Command
{
    protected $signature = 'db:create
                            {--force : Skip confirmation (useful for scripts / CI)}';

    protected $description = 'Create the MySQL/MariaDB database from config (DB_DATABASE) if it does not exist';

    public function handle(): int
    {
        $driver = config('database.default');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error('Set DB_CONNECTION=mysql (or mariadb) in .env. Current default connection: '.$driver);

            return self::FAILURE;
        }

        $connection = Config::get("database.connections.{$driver}");
        $database = (string) ($connection['database'] ?? '');

        if ($database === '') {
            $this->error('DB_DATABASE is empty. Set it in .env (e.g. scanner_lite).');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            $this->error('DB_DATABASE must use only letters, numbers, and underscores.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Create database `{$database}` if it does not exist?", true)) {
            $this->comment('Aborted.');

            return self::FAILURE;
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? 'root');
        $password = (string) ($connection['password'] ?? '');
        $charset = (string) ($connection['charset'] ?? 'utf8mb4');
        $collation = (string) ($connection['collation'] ?? 'utf8mb4_unicode_ci');
        $unixSocket = (string) ($connection['unix_socket'] ?? '');

        if ($unixSocket !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;charset=%s', $unixSocket, $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
        }

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $this->error('Could not connect to the database server: '.$e->getMessage());

            return self::FAILURE;
        }

        $safeName = '`'.str_replace('`', '``', $database).'`';

        $sql = sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s',
            $safeName,
            $charset,
            $collation
        );

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $this->error('Could not create the database: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Database `{$database}` is ready. Run: php artisan migrate");

        return self::SUCCESS;
    }
}
