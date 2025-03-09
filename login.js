document.getElementById("loginForm").addEventListener("submit", function(event) {
    event.preventDefault();
    
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    fetch("users.txt")
        .then(response => response.text())
        .then(data => {
            const users = data.split("\n").map(line => line.split(","));
            
            for (let user of users) {
                if (user[0] === email && user[1] === password) {
                    if (user[2].trim() === "admin") {
                        window.location.href = "admin_dashboard.php";
                    } else {
                        window.location.href = "user_dashboard.php";
                    }
                    return;
                }
            }
            document.getElementById("error-msg").textContent = "Invalid email or password.";
        });

        function myFunction() {
            var x = document.getElementById("myInput");
            if (x.type === "password") {
              x.type = "text";
            } else {
              x.type = "password";
            }
          }
});