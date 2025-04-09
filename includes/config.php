<?php
// Kestrel-specific security
define('KESTREL_UPLOAD_DIR', __DIR__ . '/../uploads/kestrel/');
define('KESTREL_ALLOWED_PATHS', [
    'iprs.first_name',
    'kraPortal.full_name',
    'vehicleAssets.assets.*.vehicle_no' // Wildcard support for arrays
]);
class Kever
{


    private $server = "mysql:host=srv1140.hstgr.io;dbname=u854855859_security";
    private $username = "u854855859_security";
    private $password = "6w1Gvqg[+sE$";
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

$pdo = new Kever();

$conn = $pdo->open();