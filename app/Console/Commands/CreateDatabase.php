<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class CreateDatabase extends Command
{
    protected $signature = 'db:create';
    protected $description = 'Create the database specified in .env file';

    public function handle()
    {
        $databaseName = config('database.connections.mysql.database');
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $query = "CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET $charset COLLATE $collation;";

        try {
            // Create a temporary connection without specifying the database
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');

            $dsn = "mysql:host=$host;port=$port";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create the database if it doesn't exist
            $pdo->exec($query);

            $this->info("Database `$databaseName` created successfully");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
} 