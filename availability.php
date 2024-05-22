<?php

class Availability extends Database {

    public function getData() {
        $result = "";
        $today = date('Y-m-d');
        $query = "SELECT * FROM data WHERE date = ?";
        $query = $this->con->prepare($query);
        $query->execute([$today]);

        if ($query->rowCount() > 0) {
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        else{
            $result = [];
        }

        return $result;
    }
    public function Result() {
        $today = date('Y-m-d');
        $date_checking = 'SELECT * FROM data WHERE date= ?';
        $date_checking = $this->con->prepare($date_checking);
        $date_checking->execute([$today]);
        $date_checking_result = $date_checking->fetch(PDO::FETCH_ASSOC);

        if ($date_checking->rowCount() > 0) {
            $update = true;
        }
        else {
            $update = false;  
        }

        return $update;
    }

    public function status() {
        try {
            $today = date('Y-m-d');
            $query = 'SELECT * FROM data ORDER BY id DESC LIMIT 1';
            $query = $this->con->prepare($query);
            $query->execute();
            
            if ($query->rowCount() > 0) {
                $row = $query->fetch(PDO::FETCH_ASSOC);
                $closingStock = $row['closed_stock'];
                echo "<script>document.getElementById('openStock').value = $closingStock;</script>";
            }
            else {
                
                $fetchOpenStock = "SELECT opening_stock FROM settings";
                $fetchOpenStock = $this->con->prepare($fetchOpenStock);
                $fetchOpenStock->execute();
                $openStock = $fetchOpenStock->fetch(PDO::FETCH_COLUMN);
                echo "<script>document.getElementById('openStock').value = $openStock;</script>";
            }
        } catch (PDOException $e) {
            throw new Exception("Error Processing Request: " . $e->getMessage());
        }
    }
}

$recorder = new Availability();
$recorder->status();
$recorder->Result();

?>