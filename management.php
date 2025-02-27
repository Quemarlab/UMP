<?php
require 'inc/header.php';
// Connect to your database
$conn = new PDO('mysql:host=localhost;dbname=ump', 'root', '');

$lastWeekStart = date('Y-m-d', strtotime('last Monday', strtotime('this week')));
$lastWeekEnd = date('Y-m-d', strtotime('last Sunday', strtotime('this week')));

$currentWeekStart = date('Y-m-d', strtotime('this Monday', strtotime('this week')));

$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-d');

$queryLastWeek = "SELECT SUM(recieved_day) AS total_recieved_day, SUM(recieved_night) AS total_recieved_night, 
                  SUM(recieved_total) AS total_recieved_total, SUM(issued) AS total_issued, 
                  SUM(closed_stock) AS total_closed_stock
                  FROM data 
                  WHERE recorded_on BETWEEN :lastWeekStart AND :lastWeekEnd";

$queryCurrentWeek = "SELECT SUM(recieved_day) AS total_recieved_day, SUM(recieved_night) AS total_recieved_night, 
                     SUM(recieved_total) AS total_recieved_total, SUM(issued) AS total_issued, 
                     SUM(closed_stock) AS total_closed_stock
                     FROM data 
                     WHERE recorded_on >= :currentWeekStart";



$totalMTD = "SELECT mtd FROM data ORDER BY id DESC LIMIT 1";
$totalMTD = $conn->prepare($totalMTD);
$totalMTD->execute();
$totalMTDresult = $totalMTD->fetch(PDO::FETCH_ASSOC);

$stmtLastWeek = $conn->prepare($queryLastWeek);
$stmtLastWeek->execute(['lastWeekStart' => $lastWeekStart, 'lastWeekEnd' => $lastWeekEnd]);

$stmtCurrentWeek = $conn->prepare($queryCurrentWeek);
$stmtCurrentWeek->execute(['currentWeekStart' => $currentWeekStart]);

$lastWeekData = $stmtLastWeek->fetch(PDO::FETCH_ASSOC);
$currentWeekData = $stmtCurrentWeek->fetch(PDO::FETCH_ASSOC);
?>

        <div class="container-fluid mt-3">
            <div class="row">
              <div class="col-12">
                <div class="card">

                <div class="card-header d-flex justify-content-between text-white bg-primary">
                    <div>
                        <a href="close?closeAccess"><button class="btn btn-danger"><i class="far fa-window-close"></i> Close</button></a>
                    </div>
                    <div>
                        <h4 class="text-white">UNPROCESSED MATERIALS PRODUCTION</h4>
                    </div>
                    <div id="clock" style="font-size: 24px; color: white;"></div> 
                </div>
                  <div class="card-body">
                  <ul class="nav nav-pills" id="myTab3" role="tablist">
                      <li class="nav-item">
                        <a class="nav-link active" id="dashboard-tab3" data-toggle="tab" href="#dashboard" role="tab"
                          aria-controls="dashboard" aria-selected="true">Dashboard</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="recording-tab3" data-toggle="tab" href="#recording" role="tab"
                          aria-controls="recording" aria-selected="false">Recording</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="data-tab3" data-toggle="tab" href="#data3" role="tab"
                          aria-controls="data" aria-selected="false">Data</a>
                      </li>
                    </ul>
                    <div class="feedback" style="display: none"></div>
                    <div class="tab-content" id="myTabContent2">
                      <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab3">

                      <div class="container">
                           <p>Welcome, this the UNPROCESSED MATERIALS PRODUCTION system for personal use to manage and access and analyse the data for the production increasing, so welcome dear Doc</p>
                           <h2>Monthly Production Overview</h2>
                           <p>This chart compares production metrics for the current week vs. the previous week.</p>
                           <canvas id="productionChart"></canvas>

                           <h3>Additional Analysis</h3>
                           <p>Based on the data, we can analyze trends, identify areas for improvement, and optimize production processes.</p>
                           <div class="card-body borderd">
                            <h3>Current MTD Production:</h3>
                            <h1><?php echo $totalMTDresult['mtd'] ?></h1>
                           </div>
                       </div>

                    </div>
                  <div class="tab-pane fade" id="recording" role="tabpanel" aria-labelledby="recording-tab3">
                    Use this tab to access and record all the data for the system to process and store,<br>
                    The current recording can only updated in order to consitute the Intergrity of the records and maintain the keep right record <br>
                    For further assistance, Please contact the system technician.

                  <form action="#" class="Recording" enctype="multipart/form-data">
                   <div class="form-row">
                     <div class="form-group col-md-2">
                          <label for="date">Date:</label>
                          <input type="date" class="form-control" max="<?=date('Y-m-d') ?>" value="<?=date('Y-m-d') ?>" name="date" id="date" placeholder="Enter the date" required readonly>
                        </div>
                        <div class="form-group col-md-10">
                          <label for="openStock">Opening Stock :</label>
                          <input type="number" class="form-control openStockInput" name="opening_stock" id="openStock" placeholder="Enter the opening stock amount in kgs" required readonly>
                        </div>
                      </div>
                      
                      Add received products
                      <div class="form-row">
                        <hr>
                        <div class="form-group col-md-6">
                          <label for="receivedDay">Received Day Shift (kgs):</label>
                          <input type="number" class="form-control receivedInput" name="recieved_day" id="receivedDay" placeholder="Enter the received amount for day shift" required>
                        </div>
                        <div class="form-group col-md-6">
                          <label for="receivedNight">Received Night Shift (kgs):</label>
                          <input type="number" class="form-control receivedInput" name="recieved_night" id="receivedNight" placeholder="Enter the received amount for night shift" required>
                        </div>
                      </div>
                      
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <label for="totalReceived">Total Received (kgs):</label>
                          <input type="text" class="form-control" name="recieved_total" id="totalReceived" readonly>
                        </div>
                      </div>
                      
                      Add issued product to calculate whole daily income
                      <div class="form-row">
                        <div class="form-group col-md-3">
                          <label for="grv">GRV No(Day shift).:</label>
                          <input type="text" class="form-control" name="grv_day" id="grv" placeholder="Enter the GRV number">
                        </div>
                        <div class="form-group col-md-3">
                          <label for="grv">GRV No(Night shift).:</label>
                          <input type="text" class="form-control" name="grv_night" id="grv-2" placeholder="Enter the GRV number">
                        </div>
                        <div class="form-group col-md-3">
                          <label for="issued">Issued (kgs):</label>
                          <input type="number" class="form-control issuedInput" name="issued" id="issued" placeholder="Enter the issued amount in kgs" required>
                        </div>
                        <div class="form-group col-md-3">
                          <label for="issued">GIV No:</label>
                          <input type="text" class="form-control" name="giv" id="giv" placeholder="Enter GIV No" required>
                        </div>
                        <div class="form-group col-md-12">
                          <label for="closedStock">Closed Stock (Auto-calculated):</label>
                          <input type="text" class="form-control" name="closed_stock" id="closedStock" readonly>
                        </div>
                        <?php
                        require 'availability.php';
                        $checking = new Availability();

                        $dateValid = $checking->Result();

                        $currentDateData = $checking->getData();
                        if (isset($dateValid) && $dateValid === true) {
                        $feedback = "
                        <div class='form-row'>
                        <div class='badges'><span class='badge badge-warning'>You have already recorded the data,<br> by using this page on current date, means you are going to update</span></div></div>
                        ";
                        echo $feedback;

                        echo "<script>";
                        echo "document.getElementById('openStock').value = '{$currentDateData['opening_stock']}';";
                        echo "document.getElementById('receivedDay').value = '{$currentDateData['recieved_day']}';";
                        echo "document.getElementById('receivedNight').value = '{$currentDateData['recieved_night']}';";
                        echo "document.getElementById('totalReceived').value = '{$currentDateData['recieved_total']}';";
                        echo "document.getElementById('grv').value = '{$currentDateData['grv_day']}';";
                        echo "document.getElementById('grv-2').value = '{$currentDateData['grv_night']}';";
                        echo "document.getElementById('issued').value = '{$currentDateData['issued']}';";
                        echo "document.getElementById('giv').value = '{$currentDateData['giv']}';";
                        echo "document.getElementById('closedStock').value = '{$currentDateData['closed_stock']}';";
                        echo "</script>";
                        ?>
                        <div class="form-row">
                        <div class="form-group col-md-12">
                            <input type="button" value="Update" class="btn btn-warning update">
                        </div>
                        <div class="form-group" style="display: none">
                            <input type="button" value="Record" class="btn btn-primary recorder">
                        </div>
                        </div>
                        <?php
                       }
                       else {
                        ?>
                        <div class="form-row">
                        <div class="form-group" style="display: none">
                            <input type="button" value="Update" class="btn btn-warning update">
                        </div>
                        <div class="form-group col-md-12">
                            <input type="button" value="Record" class="btn btn-primary recorder">
                        </div>
                        </div>
                        <?php
                       }
                        ?>
                      </div>
                </form>
                </div>






                      <div class="tab-pane fade" id="data3" role="tabpanel" aria-labelledby="data-tab3">
                        This tab is used to review the data and be able to download the file format of data
                        <div class="row">
                    <div class="col-12 col-md-12 col-lg-12">
                    <form action="#" class="DataForm">

                       <div class="form-row">
                        <div class="form-group col-md-6">
                          <input type="text" name="fromDate" id="fromDateInput" class="form-control datepicker" max="<?=date('Y-m-d') ?>" required>
                          </div>
                          <div class="form-group col-md-6">
                          <input type="text" name="toDate" id="toDateInput" class="form-control datepicker" max="<?=date('Y-m-d') ?>" required>
                          </div>
                          <div class="form-group col-md-1">
                          <button type="submit" class="btn btn-primary DataBtn" name="filter" id="filterButton"><i class="fas fa-search"></i>  Filter</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-striped table-hover" id="tableExport" style="width:100%;">
                        <thead>
                          <tr>
                          <th>Date</th>
                          <th>Open stock (kgs)</th>
                          <th>Received(D)</th>
                          <th>Received(N)</th>
                          <th>Total R</th>
                          <th>GRV No.(D)</th>
                          <th>GRV No.(N)</th>
                          <th>Issued (kgs)</th>
                          <th>GIV No.</th>
                          <th>Closed stock</th>
                          <th>MTD PRODUCTION</th>
                        </tr>
                      </thead>
                        <tbody class="DataBox"></tbody>
                      </table>
                    </div>
                  </div>

                      </div>
                    </div>
                  </div>
                  <div class="card-footer bg-primary text-white d-flex justify-content-between align-item-center">
                    <p>Copyright &copy; <?= date('Y') ?> UMP For Dr. Serge</p>
                    <p>Powered by - <a href="http://www.spct.ezyro.com" target="_blank" class="text-white">QDEVS Inc.</a></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
<?php
require 'inc/footer.php';

?>


<script>
    var lastWeekData = <?php echo json_encode($lastWeekData); ?>;
    var currentWeekData = <?php echo json_encode($currentWeekData); ?>;
    var labels = Object.keys(currentWeekData);

    var ctx = document.getElementById('productionChart').getContext('2d');
    var productionChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Last Week',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    data: Object.values(lastWeekData)
                },
                {
                    label: 'Current Week',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    data: Object.values(currentWeekData)
                },
            ]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
</script>
<script src="ajax/js/management.js"></script>