<?php

use depage\DB\Schema;

// {{{ DatabaseSchemaTestClassddd
class DatabaseSchemaTestClass extends Schema
{
    public function currentTableVersion($tableName)
    {
        return parent::currentTableVersion($tableName);
    }
}
// }}}

class SchemaDatabaseTest extends Generic_Tests_DatabaseTestCase
{
    // {{{ dropTestTable
    public function dropTestTable()
    {
        // table might not exist. so we catch the exception
        try {
            $preparedStatement = $this->pdo->prepare('DROP TABLE test');
            $preparedStatement->execute();
        } catch (\PDOException $expected) {}
    }
    // }}}
    // {{{ setUp
    public function setUp()
    {
        parent::setUp();
        $this->schema = new DatabaseSchemaTestClass($this->pdo);
        $this->dropTestTable();

        $this->finalShowCreate = "CREATE TABLE `test` (\n" .
        "  `uid` int(10) unsigned NOT NULL DEFAULT '0',\n" .
        "  `pid` int(10) unsigned NOT NULL DEFAULT '0',\n" .
        "  `did` int(10) unsigned NOT NULL DEFAULT '0'\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='version 0.2'";
    }
    // }}}
    // {{{ tearDown
    public function tearDown()
    {
        $this->dropTestTable();
    }
    // }}}
    // {{{ showCreateTestTable
    public function showCreateTestTable()
    {
        $statement  = $this->pdo->query('SHOW CREATE TABLE test');
        $statement->execute();
        $row        = $statement->fetch();

        return $row['Create Table'];
    }
    // }}}

    // {{{ testCompleteUpdate
    public function testCompleteUpdate()
    {
        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals($this->finalShowCreate, $this->showCreateTestTable());
    }
    // }}}
    // {{{ testIncrementalUpdates
    public function testIncrementalUpdates()
    {
        $this->schema->loadFile('Fixtures/TestFilePart.sql');

        $firstVersion = "CREATE TABLE `test` (\n" .
        "  `uid` int(10) unsigned NOT NULL DEFAULT '0',\n" .
        "  `pid` int(10) unsigned NOT NULL DEFAULT '0'\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='version 0.1'";

        $this->assertEquals($firstVersion, $this->showCreateTestTable());

        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals($this->finalShowCreate, $this->showCreateTestTable());
    }
    // }}}
    // {{{ testUpToDate
    public function testUpToDate()
    {
        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals($this->finalShowCreate, $this->showCreateTestTable());

        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals($this->finalShowCreate, $this->showCreateTestTable());
    }
    // }}}

    // {{{ testPDOException
    /**
     * @expectedException           PDOException
     * @expectedExceptionMessage    SQLSTATE[42000]: Syntax error or access violation:
     *                              1064 You have an error in your SQL syntax;
     *                              check the manual that corresponds to your MySQL server version
     *                              for the right syntax to use near '=InnoDB DEFAULT CHARSET=utf8mb4' at line 7
     */
    public function testPDOException()
    {
        $this->schema->loadFile('Fixtures/TestSyntaxError.sql');
    }
    // }}}
    // {{{ testVersionIdentifierMissingException
    /**
     * @expectedException        depage\DB\Exceptions\SchemaException
     * @expectedExceptionMessage Missing version identifier in table "test".
     */
    public function testVersionIdentifierMissingException()
    {
        // create table without version comment
        $preparedStatement = $this->pdo->prepare("CREATE TABLE test (uid int(10) unsigned NOT NULL DEFAULT '0') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $preparedStatement->execute();

        // check if it's really there
        $expected = "CREATE TABLE `test` (\n  `uid` int(10) unsigned NOT NULL DEFAULT '0'\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->assertEquals($expected, $this->showCreateTestTable());

        // trigger exception
        $this->schema->loadFile('Fixtures/TestFile.sql');
    }
    // }}}
    // {{{ testCurrentTableVersion
    public function testCurrentTableVersion()
    {
        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals('version 0.2', $this->schema->currentTableVersion('test'));
    }
    // }}}
    // {{{ testCurrentTableVersionFallback
    public function testCurrentTableVersionFallback()
    {
        $this->pdo->queryFail = 'SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = "test" LIMIT 1';
        $this->schema->loadFile('Fixtures/TestFile.sql');
        $this->assertEquals('version 0.2', $this->schema->currentTableVersion('test'));
    }
    // }}}
    // {{{ testVersionIdentifierMissingFallbackException
    /**
     * @expectedException        depage\DB\Exceptions\SchemaException
     * @expectedExceptionMessage Missing version identifier in table "test".
     */
    public function testVersionIdentifierMissingFallbackException()
    {
        // create table without version comment
        $preparedStatement = $this->pdo->prepare("CREATE TABLE test (uid int(10) unsigned NOT NULL DEFAULT '0') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $preparedStatement->execute();

        // check if it's really there
        $expected = "CREATE TABLE `test` (\n  `uid` int(10) unsigned NOT NULL DEFAULT '0'\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->assertEquals($expected, $this->showCreateTestTable());

        // make information_schema unavailable
        $this->pdo->queryFail = 'SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = "test" LIMIT 1';

        // trigger exception
        $this->schema->loadFile('Fixtures/TestFile.sql');
    }
    // }}}
}

/* vim:set ft=php sw=4 sts=4 fdm=marker et : */
