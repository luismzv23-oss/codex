<?php
$db = new mysqli('127.0.0.1', 'root', '');
if ($db->connect_error) { die('Connection failed: ' . $db->connect_error); }
$db->query('DROP DATABASE IF EXISTS codex');
$db->query('CREATE DATABASE codex');
echo 'Database codex dropped and recreated successfully.';
