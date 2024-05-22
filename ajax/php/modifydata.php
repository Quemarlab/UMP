<?php
require '../../config.php';
class Management extends Database {

    public function recordData($data){
        try {
            $sanitizedData = filter_var_array($data, FILTER_SANITIZE_STRING);
    
            if (count(array_filter($sanitizedData)) !== count($sanitizedData)) {
                throw new Exception("All input fields are required");
            }
    
           $dataidentity = date('Y-m-d');

           $this->con->beginTransaction();

           // Calculate the closed stock for updated data

           $recieved_total = $data['recieved_day'] + $data['recieved_night'];
           $closedStock = $data['opening_stock'] + $recieved_total - $data['issued'];

           $sanitizedData['recieved_total'] = $recieved_total;
           $sanitizedData['closed_stock'] = $closedStock;

           $columns = implode(",", array_keys($sanitizedData));
           $placeholders = implode(",", array_fill(0, count($sanitizedData), "?"));
           $values = array_values($sanitizedData);

           if (!empty($columns) && !empty($values)) {
               
               $placeholders = array();
               $values = array();
               
               foreach($sanitizedData as $key => $value) {
                   if($key !== 'date') { 
                       $placeholders[] = "$key = ?";
                       $values[] = $value;
                    }
                }
                
                $updateQuery = "UPDATE data SET " . implode(", ", $placeholders) . " WHERE date = ?";
                $dateValue = $sanitizedData['date'];
                
                $values[] = $dateValue;
                
                $querystatement = $this->con->prepare($updateQuery);
                $querystatement->execute($values);
                
                if($querystatement){
                    $previousMTDQuery = "SELECT MTD FROM data WHERE date < ? ORDER BY date DESC LIMIT 1";
                    $previousMTDStatement = $this->con->prepare($previousMTDQuery);
                    $previousMTDStatement->execute([$dateValue]);
                    $previousMTD = $previousMTDStatement->fetchColumn();
                    
                    if($previousMTD === false) {
                        $previousMTD = 0; 
                    }
                    
                    
                    $newMTD = $previousMTD + $sanitizedData['recieved_total'];
                    
                    $updateMTDQuery = "UPDATE data SET MTD = ? WHERE date = ?";
                    $updateMTDStatement = $this->con->prepare($updateMTDQuery);
                    $updateMTDStatement->execute([$newMTD, $dateValue]);
                    
                    $mdate = date('Y-m-d');
                    $updateModifiedQuery = "UPDATE data SET modified = ? WHERE date = ?";
                    $updateModifiedStatement = $this->con->prepare($updateModifiedQuery);
                    $updateModifiedStatement->execute([$mdate, $dateValue]);
                    
                    $this->con->commit();
                } else {
                    $this->con->rollback();
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
                The data is Successful updated
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