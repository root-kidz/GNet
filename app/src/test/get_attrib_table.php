<?php
	$mysqli = new mysqli("127.0.0.1", "SideMaster", "Inform@tico", "tcb");

	/* verificar la conexión */
	if (mysqli_connect_errno()) {
	    printf("Conexión fallida: %s\n", mysqli_connect_error());
	    exit();
	}

	$query = "SELECT * from user_sessions ORDER BY id LIMIT 5";

	if ($result = $mysqli->query($query)) {

	    /* Obtener la información del campo de cada columna */
	    while ($finfo = $result->fetch_field()) {

	        printf("Nombre:     %s<br/>", $finfo->name);
	        // printf("Tabla:    %s<br/>", $finfo->table);
	        // printf("Largo max: %d<br/>", $finfo->max_length);
	        // printf("Banderas:    %d<br/>", $finfo->flags);
	        // printf("Tipo:     %d<br/><br/>", $finfo->type);
	    }
	    $result->close();
	}

	/* cerrar la conexión */
	$mysqli->close();
?>
