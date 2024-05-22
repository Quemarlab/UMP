<?php
require '../../config.php';

class getData extends Database {
    public function getRecords($fromDate = null, $toDate = null) {
        try {
            $output = '';
            $query = "SELECT * FROM data";

            if ($fromDate && $toDate) {
                $query .= " WHERE date BETWEEN :fromDate AND :toDate";
            }

            $query = $this->con->prepare($query);

            if ($fromDate && $toDate) {
                $query->bindParam(':fromDate', $fromDate);
                $query->bindParam(':toDate', $toDate);
            }

            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
            return $result;

        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    } 
}

if(isset($_POST['action'])) {
    if($_POST['action'] == 'getRecords') {
        $fromDate = isset($_POST['fromDate']) ? $_POST['fromDate'] : null;
        $toDate = isset($_POST['toDate']) ? $_POST['toDate'] : null;

        $new_data = new getData();
        $new_data = $new_data->getRecords($fromDate, $toDate);
        echo json_encode($new_data);
    }
}
?>