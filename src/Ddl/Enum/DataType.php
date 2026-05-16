<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl\Enum;

/**
 * Enum DataType
 * Defines common SQL column data types for MySQL/MariaDB.
 * @package Rak200\SqlBuilder\Ddl\Enum
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
enum DataType: string {

    // --- Numeric ---
    case TinyInt   = 'TINYINT';
    case SmallInt  = 'SMALLINT';
    case MediumInt = 'MEDIUMINT';
    case Int       = 'INT';
    case BigInt    = 'BIGINT';
    case Float     = 'FLOAT';
    case Double    = 'DOUBLE';
    case Decimal   = 'DECIMAL';

    // --- String ---
    case Char       = 'CHAR';
    case VarChar    = 'VARCHAR';
    case TinyText   = 'TINYTEXT';
    case Text       = 'TEXT';
    case MediumText = 'MEDIUMTEXT';
    case LongText   = 'LONGTEXT';

    // --- Binary ---
    case TinyBlob   = 'TINYBLOB';
    case Blob       = 'BLOB';
    case MediumBlob = 'MEDIUMBLOB';
    case LongBlob   = 'LONGBLOB';

    // --- Date / Time ---
    case Date      = 'DATE';
    case Time      = 'TIME';
    case DateTime  = 'DATETIME';
    case Timestamp = 'TIMESTAMP';
    case Year      = 'YEAR';

    // --- Other ---
    case Boolean = 'BOOLEAN';
    case Json    = 'JSON';
    case Uuid    = 'UUID';
}
