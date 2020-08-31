<!DOCTYPE html>
<html>

<body>
    <h3 id="testLogin">You seem to be not logged in.</h3>
</body>

<script>

var xhttp = new XMLHttpRequest();
xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
        document.getElementById("testLogin").innerHTML = this.responseText;
    }
}
xhttp.open('GET', '../api/user/?q=me', true);
xhttp.send();

</script>
</html>