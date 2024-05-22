const Recording = document.querySelector('.Recording');
const recorderBtn = Recording.querySelector('.recorder');
const updateBtn = Recording.querySelector('.update');
const feedbackTxt = document.querySelector('.feedback');


updateBtn.addEventListener('click', (e) => {
    e.preventDefault();

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax/php/modifydata.php", true);
    xhr.onload = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                let data = xhr.responseText;
                if (data.includes("Successful")) {
                    feedbackTxt.style.display = "block";
                    feedbackTxt.innerHTML = data;
                    Recording.reset();

                    getDataRecords();
                } else {
                    feedbackTxt.style.display = "block";
                    feedbackTxt.innerHTML = data;
                }
            } else {
                console.log("Error occurred: " + xhr.status);
            }
        }
    }

    let formData = new FormData(Recording);
    xhr.send(formData); 
});


recorderBtn.addEventListener('click', (e) => {
    e.preventDefault();

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax/php/management.php", true);
    xhr.onload = () => {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                let data = xhr.responseText;
                if (data.includes("Successful")) {
                    feedbackTxt.style.display = "block";
                    feedbackTxt.innerHTML = data;
                    Recording.reset();

                    getDataRecords();
                } else {
                    feedbackTxt.style.display = "block";
                    feedbackTxt.innerHTML = data;
                }
            } else {
                console.log("Error occurred: " + xhr.status);
            }
        }
    }

    let formData = new FormData(Recording);
    xhr.send(formData); 
});



function getDataRecords(fromDate, toDate) {
    $.ajax({
        url: 'ajax/php/getdata.php',
        type: 'POST',
        data: { action: 'getRecords', fromDate: fromDate, toDate: toDate },
        success: function(data) {
            if ($.fn.DataTable.isDataTable('#tableExport')) {
                $('#tableExport').DataTable().destroy();
            }

            $('#tableExport').DataTable({
                paging: true,
                searching: true,
                data: JSON.parse(data),
                columns: [
                    { data: 'date' },
                    { data: 'opening_stock' },
                    { data: 'recieved_day' },
                    { data: 'recieved_night' },
                    { data: 'recieved_total' },
                    { data: 'grv_day' },
                    { data: 'grv_night' },
                    { data: 'issued' },
                    { data: 'giv' },
                    { data: 'closed_stock' },
                    { data: 'mtd' }
                ],
                dom: 'Bfrtip',
                buttons: [
                {
                    extend: 'copy',
                    filename: 'UMP-Data on '+ new Date().toLocaleDateString(),
                    title: 'Unprocessed Material Production',
                },
                {
                    extend: 'csv',
                    filename: 'UMP-Data on '+ new Date().toLocaleDateString(),
                    title: 'Unprocessed Material Production',
                },
                {
                    extend: 'excel',
                    filename: 'UMP-Data on '+ new Date().toLocaleDateString(),
                    title: 'Unprocessed Material Production',
                },
                {
                    extend: 'pdf',
                    filename: 'UMP-Data on '+ new Date().toLocaleDateString(),
                    title: 'Unprocessed Material Production',
                },
                {
                    extend: 'print',
                    title: 'Unprocessed Material Production',
                },
            ]
        });
    }
    });
}



$(document).ready(function() {
    getDataRecords(); 

    $('#filterButton').on('click', function(e) {
        e.preventDefault();
        var fromDate = $('#fromDateInput').val();
        var toDate = $('#toDateInput').val();
        getDataRecords(fromDate, toDate);
    });
});









    document.querySelectorAll('.receivedInput').forEach(input => {
        input.addEventListener('input', function() {
            const receivedDay = parseFloat(document.getElementById('receivedDay').value) || 0;
            const receivedNight = parseFloat(document.getElementById('receivedNight').value) || 0;
            const totalReceived = receivedDay + receivedNight;
            document.getElementById('totalReceived').value = totalReceived;
        });
    });

    document.querySelectorAll('.receivedInput, .issuedInput').forEach(input => {
        input.addEventListener('input', function() {
            const openingStock = parseFloat(document.getElementById('openStock').value) || 0;
            const received = parseFloat(document.getElementById('totalReceived').value) || 0;
            const issued = parseFloat(document.getElementById('issued').value) || 0;
            const closedStock = openingStock + received - issued;
            document.getElementById('closedStock').value = closedStock;
        });
    });