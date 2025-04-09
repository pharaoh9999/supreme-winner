<?php
class AutoConn
{


    private $server = "mysql:host=srv1140.hstgr.io;dbname=u854855859_auto_kever";
    private $username = "u854855859_auto_kever";
    private $password = "C#XQPoH*2q";
    private $options  = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true,  // Use persistent connections
    );

    protected $conn;

    public function open()
    {
        try {
            $this->conn = new PDO($this->server, $this->username, $this->password, $this->options);
            return $this->conn;
        } catch (PDOException $e) {
            echo "There is some problem in connection: " . $e->getMessage();
        }
    }

    public function close()
    {
        $this->conn = null;
    }
}

//$dbFile = 'nationPersons.db';
$start = date('Y-m-d H:i:s');
//*


$pdo = new AutoConn();

$conn3 = $pdo->open();
