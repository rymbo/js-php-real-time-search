<?php
/**
 *
 * PHP version 5.4
 *
 * @category  GLICER
 * @package   GlSearch
 * @author    Emmanuel ROECKER
 * @author    Rym BOUCHAGOUR
 * @copyright 2015 GLICER
 * @license   GNU 2
 * @link      http://dev.glicer.com/
 *
 * Created : 24/07/15
 * File : GlServerEngine.php
 *
 */

namespace GlSearchEngine;

use Symfony\Component\Console\Output\OutputInterface;


class GlServerIndex
{
    /**
     * @var \SQLite3
     */
    private $db;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $tableFilter;

    /**
     * @var string
     */
    private $tableFullText;

    /**
     * @var string
     */
    private $sqlfieldsFilter;

    /**
     * @var string
     */
    private $sqlfieldsFullText;

    /**
     * @var array
     */
    private $fieldsFullText;

    /**
     * @var array
     */
    private $fieldsFilter;

    /**
     * @param string $s
     *
     * @throws \Exception
     * @return string
     */
    private function normalizeUtf8String($s)
    {
        if (!class_exists("Normalizer", $autoload = false)) {
            throw new \Exception('Normalizer-class missing ! ');
        }

        $original_string = $s;

        $s = preg_replace('@\x{00c4}@u', "AE", $s);
        $s = preg_replace('@\x{00d6}@u', "OE", $s);
        $s = preg_replace('@\x{00dc}@u', "UE", $s);
        $s = preg_replace('@\x{00e4}@u', "ae", $s);
        $s = preg_replace('@\x{00f6}@u', "oe", $s);
        $s = preg_replace('@\x{00fc}@u', "ue", $s);
        $s = preg_replace('@\x{00f1}@u', "ny", $s);
        $s = preg_replace('@\x{00ff}@u', "yu", $s);

        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);

        $s = preg_replace('@\pM@u', "", $s);

        $s = preg_replace('@\x{00df}@u', "ss", $s);
        $s = preg_replace('@\x{00c6}@u', "AE", $s);
        $s = preg_replace('@\x{00e6}@u', "ae", $s);
        $s = preg_replace('@\x{0132}@u', "IJ", $s);
        $s = preg_replace('@\x{0133}@u', "ij", $s);
        $s = preg_replace('@\x{0152}@u', "OE", $s);
        $s = preg_replace('@\x{0153}@u', "oe", $s);

        $s = preg_replace('@\x{00d0}@u', "D", $s);
        $s = preg_replace('@\x{0110}@u', "D", $s);
        $s = preg_replace('@\x{00f0}@u', "d", $s);
        $s = preg_replace('@\x{0111}@u', "d", $s);
        $s = preg_replace('@\x{0126}@u', "H", $s);
        $s = preg_replace('@\x{0127}@u', "h", $s);
        $s = preg_replace('@\x{0131}@u', "i", $s);
        $s = preg_replace('@\x{0138}@u', "k", $s);
        $s = preg_replace('@\x{013f}@u', "L", $s);
        $s = preg_replace('@\x{0141}@u', "L", $s);
        $s = preg_replace('@\x{0140}@u', "l", $s);
        $s = preg_replace('@\x{0142}@u', "l", $s);
        $s = preg_replace('@\x{014a}@u', "N", $s);
        $s = preg_replace('@\x{0149}@u', "n", $s);
        $s = preg_replace('@\x{014b}@u', "n", $s);
        $s = preg_replace('@\x{00d8}@u', "O", $s);
        $s = preg_replace('@\x{00f8}@u', "o", $s);
        $s = preg_replace('@\x{017f}@u', "s", $s);
        $s = preg_replace('@\x{00de}@u', "T", $s);
        $s = preg_replace('@\x{0166}@u', "T", $s);
        $s = preg_replace('@\x{00fe}@u', "t", $s);
        $s = preg_replace('@\x{0167}@u', "t", $s);

        $s = preg_replace('@[^\0-\x80]@u', "", $s);

        if (empty($s)) {
            return $original_string;
        } else {
            return $s;
        }
    }

    /**
     * @param string $s
     *
     * @return mixed
     */
    private function normalize($s)
    {
        return strtolower(preg_replace('/\r\n?/', "", \SQLite3::escapeString($this->normalizeUtf8String($s))));
    }

    /**
     * @param \SQLite3        $db
     * @param string          $table
     * @param array           $fieldsFilter
     * @param array           $fieldsFullText
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    public function __construct($db, $table, array $fieldsFilter, array $fieldsFullText, OutputInterface $output)
    {
        $this->output = $output;
        $this->db     = $db;

        $this->fieldsFilter   = $fieldsFilter;
        $this->fieldsFullText = $fieldsFullText;
        $this->tableFilter    = "{$table}F";
        $this->tableFullText  = "{$table}FT";

        if (sizeof($fieldsFilter) > 0) {
            $this->sqlfieldsFilter = implode("','", $fieldsFilter);
            $createSQLFilter       = "CREATE TABLE {$this->tableFilter}(docid INTEGER PRIMARY KEY, uid UNIQUE, json, '{$this->sqlfieldsFilter}')";
        } else {
            $this->sqlfieldsFilter = null;
            $createSQLFilter       = "CREATE TABLE {$this->tableFilter}(docid INTEGER PRIMARY KEY, uid UNIQUE, json)";
        }

        if ($this->db->exec($createSQLFilter) === false) {
            $this->output->writeln($createSQLFilter);
            $this->output->writeln($this->db->lastErrorCode() . " : " . $this->db->lastErrorMsg());
            throw new \Exception("cannot create table : " . $this->tableFilter);
        }

        if (sizeof($fieldsFullText) <= 0) {

            throw new \Exception("You must have at least one field full text");
        }

        $this->sqlfieldsFullText = implode("','", $fieldsFullText);
        $createSQLFullText       = "CREATE VIRTUAL TABLE {$this->tableFullText} USING fts4('{$this->sqlfieldsFullText}');";
        if ($this->db->exec($createSQLFullText) === false) {
            $this->output->writeln($createSQLFullText);
            $this->output->writeln($this->db->lastErrorCode() . " : " . $this->db->lastErrorMsg());
            throw new \Exception("cannot create table : " . $this->tableFullText);
        }
    }

    /**
     * @param int      $id
     * @param array    $data
     * @param callable $callback
     *
     * @throws \Exception
     */
    public function import(
        &$id,
        array $data,
        callable $callback
    ) {
        foreach ($data as $uid => $elem) {
            $values = [];
            foreach ($this->fieldsFullText as $field) {
                if (isset($elem[$field])) {
                    $values[$field] = $this->normalize($elem[$field]);
                } else {
                    $values[$field] = '';
                }
            }
            if (sizeof($values) > 0) {
                $json         = \SQLite3::escapeString(json_encode($elem));
                $valuesString = implode("','", $values);

                $insertSQL = "INSERT INTO {$this->tableFilter} VALUES ($id, '$uid', '$json')";
                if (@$this->db->exec($insertSQL) === false) {
                    $lasterror = $this->db->lastErrorCode();
                    if ($lasterror != 19) {
                        $this->output->writeln($insertSQL);
                        $this->output->writeln($lasterror . " : " . $this->db->lastErrorMsg());
                        throw new \Exception("cannot insert");
                    } else {
                        continue;
                    }
                }

                $insertSQL = "INSERT INTO {$this->tableFullText}(docid,'{$this->sqlfieldsFullText}') VALUES ($id,'$valuesString')";
                if ($this->db->exec($insertSQL) === false) {
                    $this->output->writeln($insertSQL);
                    $this->output->writeln($this->db->lastErrorCode() . " : " . $this->db->lastErrorMsg());
                    throw new \Exception("cannot insert");
                }
                $id++;
            }
            $callback();
        }
    }
} 