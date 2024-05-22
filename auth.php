<?php

require 'config.php';

class Authentication extends Database {
    private $hashedPassword;

    public function __construct() {
        parent::__construct();
    
        $query = "SELECT password FROM settings WHERE id = ?";
        $stmt = $this->con->prepare($query);
        
        if ($stmt) {
            $stmt->execute([1]);
    
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->hashedPassword = $row['password'];
            } else {
                echo "<script>
                alert('Error: Password not found in settings table. This tab will be closed.');
                window.close();
                self.location=index;
                </script>";
                exit();
            }
        } else {
            echo "<script>
            alert('Error: Failed to prepare the SQL query. This tab will be closed.'); 
            window.close();
            self.location=index;
            </script>";
            exit();
        }
    }

    public function authenticate($password) {
        if (password_verify($password, $this->hashedPassword)) {
            $_SESSION['authenticated'] = true;
            header('Location: management');
            exit();
        } else {
            echo "<script>
            alert('Incorrect password. This tab will be closed.'); 
            window.close();
            self.location='index';
            </script>";
            exit();
        }
    }
}

$auth = new Authentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $auth->authenticate($password);
}

?>