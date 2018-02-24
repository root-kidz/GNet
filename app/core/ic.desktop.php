<!DOCTYPE html>
<html lang="en">
	<head>
		<?php 
			
			#Importar constantes.
			// include (@$_SESSION['getConsts']);

			#Agregando fichero head del core.
			include (PF_CORE_HEAD); 
		?>
	</head>
		<?php
			#Dependendiendo del contenido de la variable de sesión
			# p se decidirá que mostrarle al usuario.

			#En caso que sea Root, se muestra el main.php del Root.
			if (@$_SESSION['p'] == "root")
				include (PD_DESKTOP_ROOT."/main.php");
			else if (@$_SESSION['p'] == "admin")
				include (PD_DESKTOP_ADMIN."/main.php");

			#Todas tiene constantes que hacen referencia a la ruta 
			#de cada fichero con respecto al privilegio.
		?>
</html>
