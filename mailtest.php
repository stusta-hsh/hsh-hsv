<?php

if(mail("derfesl@outlook.com", "Login to HSH", "To complete your registration, click this link:", "from: hsh@stusta.de")) {
    echo "gesendet";
} else {
    echo "nicht geklappt";
}

?>