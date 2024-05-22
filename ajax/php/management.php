<?php
require '../../config.php';
class Management extends Database {

    public function recordData($data){
        try {
            $sanitizedData = filter_var_array($data, FILTER_SANITIZE_STRING);
    
            if (count(array_filter($sanitizedData)) !== count($sanitizedData)) {
                throw new Exception("All input fields are required");
            }
    
            $date = $sanitizedData['date'];
    
            $check_record = "SELECT * FROM data";
            $check_record = $this->con->prepare($check_record);
            $check_record->execute();
    
            if ($check_record->rowCount() == 0) {
                $get_settings = "SELECT opening_stock, mtd FROM settings";
                $get_settings = $this->con->prepare($get_settings);
                $get_settings->execute();
                $settings = $get_settings->fetch(PDO::FETCH_ASSOC);
    
                $sanitizedData['opening_stock'] = $settings['opening_stock'];
                $sanitizedData['mtd'] = $settings['mtd'];
            }
    
            $code = "UMP" . rand(1, 99099);
            $sanitizedData["code"] = $code;
            
            $check_availability = "SELECT mtd FROM data ORDER BY id DESC LIMIT 1";
            $check_availability = $this->con->prepare($check_availability);
            $check_availability->execute();

            $mtd = 0;
            if ($check_availability->rowCount() > 0) {
                $lastRow = $check_availability->fetch(PDO::FETCH_ASSOC);
                $mtd = $lastRow['mtd'];
            }

            $columns = implode(",", array_keys($sanitizedData));
            $placeholders = implode(",", array_fill(0, count($sanitizedData), "?"));
            $values = array_values($sanitizedData);
    
            if (!empty($columns) && !empty($values)) {
                $this->con->beginTransaction();

                $save_record = "INSERT INTO data ($columns) VALUES ($placeholders)";
                $save_record = $this->con->prepare($save_record);
                $save_record->execute($values);

                $newMTD = $mtd + $data['recieved_total'];
                $update_mtd = "UPDATE data SET mtd = ? WHERE code = ?";
                $update_mtd = $this->con->prepare($update_mtd);
                $update_mtd->execute([$newMTD, $code]);

                if ($save_record && $update_mtd) {
                    $this->con->commit();
                } else {
                    $this->con->rollBack();
                    throw new Exception("Error processing request");
                }
            }
    
        } catch (PDOException $e) {
            throw new Exception("Database error: " . $e->getMessage());
        } catch (Exception $e) {
            throw $e;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    try {
        $object = new Management();
        $object->recordData($_POST);
        
        echo "<div class='alert alert-success alert-dismissible show fade'>
            <div class='alert-body'>
                <button class='close' data-dismiss='alert'><span>×</span></button>
                The data is Successful recorded
            </div>
        </div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger alert-dismissible show fade'>
            <div class='alert-body'>
                <button class='close' data-dismiss='alert'><span>×</span></button>
                An error occurred: " . $e->getMessage() . "
            </div>
        </div>";
    }
}


?>