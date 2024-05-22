<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authentication</title>
    <link rel='shortcut icon' type='image/x-icon' href='assets/img/logo.png' />

</head>
<body>
    <script>
        let password = prompt("Enter Password:");
        
        if (password) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.setAttribute('action', 'auth.php'); 

            let passwordInput = document.createElement('input');
            passwordInput.setAttribute('type', 'hidden');
            passwordInput.setAttribute('name', 'password');
            passwordInput.setAttribute('value', password);
            form.appendChild(passwordInput);

            document.body.appendChild(form);
            form.submit();
        } else {
            alert('No password entered. This tab will be closed.');
            window.open('','_parent','');
            window.close();
        }
    </script>
</body>
</html>