<?php

class DataBase
{
    public function __construct($host = "jora13y6.beget.tech", $user = "jora13y6_eup", $password = "3EuvV*nS", $database = "jora13y6_eup")
    {
        $this->DataBase = new mysqli($host, $user, $password, $database);
        if ($this->DataBase->connect_errno) {
            throw new Exception("Failed to connect to MySQL: (" . $this->DataBase->connect_errno . ") " . $this->DataBase->connect_error);
            print $this->DataBase->host_info . "\n";
        }
        return $this->DataBase;
    }
    public function query($query, $close = false)
    {
        $result = $this->DataBase->query($query);
        if ($this->DataBase->errno) {
            throw new Exception("Failed to send a query to MySQL: (" . $this->DataBase->errno . ") " . $this->DataBase->error);
            print $this->DataBase->host_info . "\n";
        }
        if ($close) $this->DataBase->close();
        return $result;
    }
}

class MysqliListener
{
    private $listeners = [];
    private $counter;
    public $timeout = 0;
    public $loop = 0;
    public function __construct($timeout = false)
    {
        if ($timeout) $this->timeout = $timeout;
    }

    public function AddCounterTo($table)
    {
        $mysqli = new DataBase();
        $mysqli->query("ALTER TABLE $table ADD COLUMN MysqliListenerCounter int(255) NOT NULL");
        $mysqli->query("
        CREATE TRIGGER onUpdate$table BEFORE UPDATE ON $table
            FOR EACH ROW
                BEGIN
                    set new.MysqliListenerCounter = old.MysqliListenerCounter+1; 
                END
        ");
        $mysqli->query("UPDATE for_add SET status = '900' WHERE id = '-1'", true);
    }

    public function RemoveCounterFrom($table)
    {
        $mysqli = new DataBase();
        $mysqli->query("DROP TRIGGER IF EXISTS onUpdate$table");
        $mysqli->query("ALTER TABLE $table DROP COLUMN MysqliListenerCounter", true);
    }

    public function AddListener(string $name, string $query, int $default)
    {
        preg_match("/SELECT (?P<select>.+) FROM (?P<from>\w+) WHERE (?P<where>.+)/i", $query, $query);
        if (empty($query)) throw new Exception("invalid query format");
        
        $select = explode(", ", $query['select']);
        $this->listeners['onUpdate'][] = [
            'name' => $name,
            'ListenTo' => $select,
            'WhereIs' => $query['from'],
            'at' => $query['where'],
            'default' => $default,
        ];
    }

    public function listen()
    {
        foreach ($this->listeners['onUpdate'] as $listener) {
            $ListenTo = implode(", ", $listener['ListenTo']);
            $mysqli = new DataBase();
            $row = $mysqli->query("SELECT $ListenTo, MysqliListenerCounter FROM {$listener['WhereIs']} WHERE {$listener['at']}")->fetch_assoc();
            if ($row['MysqliListenerCounter'] >= $listener['default']) $rows[] = $row;
        }
        if (isset($rows)) print_r(json_encode($rows));
    }

    public function loop($function = false)
    {
        while (true) {
            if ($function) $function()->current();
            $this->loop++;
            sleep($this->timeout);
        }
    }
}

?>