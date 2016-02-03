<?php
/**
 *
 * Users table class
 * extends Zend_Db_Table_Abstract and redefines insert, upadate and delete methods
 *
 * @autor Aleksandar Markicevic <aleksandar.markicevic@golive.rs>
 */
class My_Db_Table extends Zend_Db_Table_Abstract
{

    /**
     * Inserts a new row.
     *
     * @param  array  $data  Column-value pairs.
     * @return mixed         The primary key of the row inserted.
     */
    public function insert(array $data)
    {
        $this->_setupPrimaryKey();

        /**
         * Zend_Db_Table assumes that if you have a compound primary key
         * and one of the columns in the key uses a sequence,
         * it's the _first_ column in the compound key.
         */
        $primary = (array) $this->_primary;
        $pkIdentity = $primary[(int)$this->_identity];

        /**
         * If this table uses a database sequence object and the data does not
         * specify a value, then get the next ID from the sequence and add it
         * to the row.  We assume that only the first column in a compound
         * primary key takes a value from a sequence.
         */
        if (is_string($this->_sequence) && !isset($data[$pkIdentity])) {
            $data[$pkIdentity] = $this->_db->nextSequenceId($this->_sequence);
            $pkSuppliedBySequence = true;
        }

        /**
         * If the primary key can be generated automatically, and no value was
         * specified in the user-supplied data, then omit it from the tuple.
         *
         * Note: this checks for sensible values in the supplied primary key
         * position of the data.  The following values are considered empty:
         *   null, false, true, '', array()
         */
        if (!isset($pkSuppliedBySequence) && array_key_exists($pkIdentity, $data)) {
            if ($data[$pkIdentity] === null                                        // null
            || $data[$pkIdentity] === ''                                       // empty string
            || is_bool($data[$pkIdentity])                                     // boolean
            || (is_array($data[$pkIdentity]) && empty($data[$pkIdentity]))) {  // empty array
                unset($data[$pkIdentity]);
            }
        }

        /**
         * INSERT the new row.
         */
        $tableSpec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        $this->_db->insert($tableSpec, $data);

        /**
         * Fetch the most recent ID generated by an auto-increment
         * or IDENTITY column, unless the user has specified a value,
         * overriding the auto-increment mechanism.
         */
        if ($this->_sequence === true && !isset($data[$pkIdentity])) {
            $data[$pkIdentity] = $this->_db->lastInsertId();
        }

        /**
         * Return the primary key value if the PK is a single column,
         * else return an associative array of the PK column/value pairs.
         */

        $pkData = array_intersect_key($data, array_flip($primary));
            
        //if row is inserted log the action in change_log table
        if ($pkData) {
            $changed_table_name = $this->_name;
            $user_action = 'INSERT';
            $log_model = new Admin_Model_LogAdminActions();
            $log_model->logDbAction($changed_table_name, $user_action, $data);
        }

        if (count($primary) == 1) {
            reset($pkData);
            return current($pkData);
        }

        return $pkData;
    }
    /**
     * Updates existing rows.
     *
     * @param  array        $data  Column-value pairs.
     * @param  array|string $where An SQL WHERE clause, or an array of SQL WHERE clauses.
     * @return int          The number of rows updated.
     */
    public function update(array $data, $where)
    {
        $tableSpec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        $result = $this->_db->update($tableSpec, $data, $where);

        //if row is updated log the action in change_log table
        if ($result) {
            $changed_table_name = $this->_name;
            $user_action = 'UPDATE';
            $row_id = str_replace('?', reset($where), key($where));
            if (!$row_id && is_array($where)) {
                $row_id = '';
                foreach ($where as $value) {
                    $row_id .= $value.' ';
                }
            }
            $log_model = new Admin_Model_LogAdminActions();
            $log_model->logDbAction($changed_table_name, $user_action, $data, $row_id);
        }

        return $result;
    }

    /**
     * Deletes existing rows.
     *
     * @param  array|string $where SQL WHERE clause(s).
     * @return int          The number of rows deleted.
     */
    public function delete($where)
    {
        $tableSpec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        $result = $this->_db->delete($tableSpec, $where);

        //if row is deleted log the action in change_log table
        if ($result) {
            $changed_table_name = $this->_name;
            $user_action = 'DELETE';
            $log_model = new Admin_Model_LogAdminActions();
            $log_model->logDbAction($changed_table_name, $user_action, $where);
        }

        return $result;
    }
}
