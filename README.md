# DisEngine-ESB
Server engine based on event system.

## Overview
Customizable engine that supports endless expansion. Server side uses events to share changes in data with clients. Clients receive information via EventStream protocol.

Consists of 2 parts:
- API. Handles requests and raises events. Client-to-server connection.
- EventStream. Waits for events and sends updates to a client. Server-to-client connection.

## EventSystem
### Event creation
For each database table a separate class is required. Each class' fields should mimic the table structure (data type, restrictions, etc.). There are only 3 event types for a class:
- added (record added to the table)
- changed (record is changed)
- deleted (record was deleted)

After a class' method 'update' or 'delete' succeeds the event system is notified.

### Event raising
The event system is notified after data changes. 'raise' method accepts class name and an event type. Actual raised event name will be: <className>-<eventType>, where <className> is the name of representing class for some DB table (class name could be different from actual Db table it represents) and <eventType> is one of 3: 'added', 'changed', 'deleted'.

### Event processing
Each client will have one or more EventStream connections with the server. For each connection a certain number of subsriptions will be provided by the client. Subsriptions decide which of server events the client is notified of.

## Classes overview
### Base classes
Engine has 5 base classes to build the application upon:
1. DBField. Represents a single field in a DB table.
2. DBRecord. Represents a single record in a DB table.
3. DBRecordGroup. Represents a joined record in a DB table where a record in the main table has several dependant records in another tables.
4. DBSelector. A class to perform SELECT queries on a single DB table (DBRecord attached).
5. DBRGSelector. A class to perform SELECT queries on joined tables (DBRecordGroup).

### DBField
Represents a base class for fields in a DB table. Currently 3 field types are supported: Int, String, DateTime. Each class instance can specify an exact DB type this instance uses and a few optional parameters.
```
// Numeric class - NumData
$int_field = new NumData('int_field', 'INT');           // Creates a field of INT type
$short_field = new NumData('short_field', 'INT(1)');    // Creates a field of INT(1) type
$int_field->setRange($min, $max);                       // Set min-max range for this field

// String class - StrData
$text_field = new StrData('text_field', 'TEXT');            // Creates a field of TEXT type
$str_field = new StrData('varchar_field', 'VARCHAR(250)');  // Creates a field of VARCHAR(250) type
$str_field->setPattern('/^\w+$/');                          // Sets a pattern to match
$str_field->setLengthRange($min, $max);                     // Min-max length of this field

// DataTime class - DateTimeData. Keeps string representation of data type
$dt_field = new DateTimeData('updated', 'DATETIME');    // Creates a field of DATETIME type
$dt_field->setRange('2010-01-01', '2015-01-01');        // Sets data range   

// All classes
$f->setCustomCheck('foo');      // Specify a custom value check function
$f->allowChange(true/false);    // Set whether or not value of this field can be changed after assigning
$f->allowNull(true/false);      // Set whether or not NULL value is supported
$f->setValue($val);             // Set new value for this field. Returns false on fail
$f->getValue();                 // Get string represenstation of current value
```
Field types should reflect the types used in DB tables, otherwise database queries will fail. Default values for fields can be configured in 'engine_fields.php' file by changing these constants.
```
define('MAX_INT', PHP_MAX_INT);
define('DEFAULT_CAN_NULL', false);
define('DEFAULT_CAN_CHANGE', true);
define('DEFAULT_CHECK_F_VALUE', null);
define('DEFAULT_INT_TYPE', 'INT');
define('DEFAULT_STR_TYPE', 'VARCHAR(45)');
define('DEFAULT_STR_MINLENGTH', 0);
define('DEFAULT_STR_MAXLENGTH', PHP_MAX_INT);
define('DEFAULT_STR_PATTERN', '/^.*$/');
define('DEFAULT_DATETIME_TYPE', 'DATETIME');
```

It is possible to expand the number of classes (beyond default 3) manually if required but it is not described here.

### DBRecord
Represents a base class for one record in DB table. For each DB table a new class should be inherited from this one. Typical class description is as follows:
```
<?php
// Creating new class to represent Accounts table. PHP file name should reflect the class name. In this case 'Account.php';

require_once 'engine_record.php'; // Base class

// Inherit base class
class Account extends DisEngine\DBRecord {
    function __construct(){
        parent::__construct('Accounts');    // Call to parent construct with table's name
        
        // Adding fields
        $id_field = new NumData('id', 'INT');
        $id_field->allowChange(false);
        $id_field->allowNull(false);
        $this->addField($id_field);
        
        $login_field = new StrData('login', 'VARCHAR(32)');
        $login_field->allowChange(true);
        $login_field->allowNull(false);
        $this->addField($login_field);
        
        $psw_field = new StrData('pswHash', 'VARCHAR(255)');
        $psw_field->allowChange(true);
        $psw_field->allowNull(true);
        $this->addField($psw_field);
        
        $reg_field = new DateTimeData('registered');    // Will default to 'DATETIME' if not specified
        $reg_field->allowChange(false);
        $reg_field->allowNull(true);
        $this->addField($reg_field);
    }
}
?>
```
Setting and getting field values can be done as follows:
```
$new_account = new Account();
$new_account->fillData(['id' => 3, 'login' => 'newuser', 'pswHash' => '$423fhg$ghjhg44%&*']);
$login = $new_account->getField('login');
```

### DBRecordGroup
Represents a base class for working with records which have dependant records in other tables. This class uses each class for each table it supports. Typical description is as follows:
```
<?php
// Creating new class to represent Groups table. PHP file name should reflect the class name. In this case 'Groups.php';
// Groups table has GroupMember vector table that's used to contain group member list.
// GroupMember table is represented by GroupMember class (class name can be different).

require_once 'engine_rg.php'; // Base class (DBRecordGroup)

// Inherit base class
class Groups extends DisEngine\DBRecordGroup {
    function __construct(){
        // There should be a 'Group.php' file containing Group class.
        // Group class represents Groups table.
        parent::__construct('Group');   // Call to parent construct with main class name
        
        // Adding dependants
        $this->addCat(['table' => 'GroupMember', 'class' => 'GroupMember', 'dep_field' => 'group_id');
    }
}
?>
```
For each dependant table vector 3 things should be specified:
- Table name
- Class name that represents the table (DBRecord child)
- 'dep_field' - Name of the field in the table that is a foreign key. This value will be used as basis for JOIN operations in SELECT queries for this data.
