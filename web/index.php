<!DOCTYPE html>
<html>

<script src="../lib/jquery-3.5.1.js"></script>

<body>
    <h3 id="testLogin">You seem to be not logged in.</h3>
</body>

<script>

$("#testLogin").load("../api/user/?q=me");

</script>
</html>