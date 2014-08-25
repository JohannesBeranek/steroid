<?php

require_once __DIR__ . '/interface.IDB.php';
require_once __DIR__ . '/interface.IFileStore.php';

interface IStorage extends IDB, IFileStore {}

?>