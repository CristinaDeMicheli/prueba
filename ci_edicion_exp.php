<?php
/**
	* Created by Marina Barrios
	*/

	require_once('consultas/consultas.php');

	class ci_edicion_exp extends staf_ci
	{
		protected $s__nro_acta = null;
		protected $s__tramite = null;
		protected $s__prinew = null;
		protected $s__usuario = false;
		protected $s__infnew = null; //tipo de infraccion modificada
		protected $s__distintop = false; //la prioridad es distinta?
		protected $s__distintoi = false; //la infracción es distinta?
		protected $s__motivo = null;
		protected $s__resorteo = false;
		protected $s__continuar = true;
		protected $s__apyn = null;
		protected $s__acta_nueva = false;
		protected $s__fila_acta = null; //guardo el acta_completa p ver si se modifico, si se modifico verifica que la nueva acta no exista en la bd ni en el dt
		protected $s__fila_imagen = null; 
		protected $s__idExpe = null;
		protected $s__usuMod = null;
		protected $s__fechaMod = null;
		protected $s__usuAlta = null;
		protected $s__numExpe = null;
		protected $s__fechacarga = null;
		#- Rutas para imágenes actas
		protected $s__carpeta_imagenes = null; 
		protected $s__carpeta_imagenes_absoluto  = null; 
		protected $s__entorno_local = false; 
		protected $s__img_tratar = array();
		#- Rutas para imagenes acciones
		protected $s__fila_acciones = null;
		protected $s__acciones_nuevo = false;
		protected $s__fila_imagen_acciones = null; 
		//protected $s__carpeta_imagenes = null; 
		//protected $s__carpeta_imagenes_absoluto  = null; 
		//protected $s__entorno_local = false; 
		//protected $s__img_tratar = array();
		protected $s__img_salvadas = array();
        protected $s__tt_provincia = null;
        protected $s__tribunal_dependencia = null; 

	


//--------------------------------------------------------------------------------------------
	function get_relacion()
	{
		return $this->controlador->dep('dr_mov_acta_exp');
	}
//--------------------------------------------------------------------------------------------
//---------------Metodo devolver, para enviar variables globales a otro CI--------------------
//--------------------------------------------------------------------------------------------
	function devolver(){
		return array('carpeta_doc'=>$this->s__carpeta_imagenes_absoluto,
			'img_tratar'=>$this->s__img_tratar,
			'entorno_local'=>$this->s__entorno_local);
	}
//--------------------------------------------------------------------------------------------
	#------ función con ajax para leer los datos de un Acta
	// function ajax__get_datos_unicidad($id, toba_ajax_respuesta $respuesta)
	// {    
		
	// 	$valor=false;
	// 	$datos = $this->get_relacion()->tabla('acta')->get_filas();
	// 	$cantidad=count($datos);
	// 	if(!$this->get_relacion()->tabla('acta')->hay_cursor()) //seleccion
	// 	{
	// 		for ($i=0; $i < $cantidad ; $i++) { 
	// 		// $valor[$i]=array('Infractor'=>$datos[$i]['id_tipoi'],$id[0],
	// 		// 				'acta'=>$datos[$i]['ac_nro'],$id[1],
	// 		// 	'anio'=>$datos[$i]['ac_anio'],$id[2],
	// 		// 	'valor'=>false);
	// 		if (($datos[$i]['id_tipoi'] == $id[0]) && ($datos[$i]['ac_nro'] == $id[1]) && ($datos[$i]['ac_anio'] == $id[2])){
	// 			$valor=true;
				
	// 			}
			
	// 	}
	// 	}
		
	// 	$respuesta->set($valor);
	// }

//--------------------------------------------------------------------------------------------
	function ini()
	{
		#- Ruta para imágenes 
		$rs = toba::db()->consultar("SELECT valor FROM tribunal.configuracion WHERE id_parametro = 'RUTA_IMAGEN'");
		$this->s__carpeta_imagenes = $rs[0]['valor'];
		$this->s__carpeta_imagenes_absoluto = '/var/www'.$rs[0]['valor'];

		if ($this->conexion()){
			$this->s__entorno_local = true;
		}else{
			$this->s__entorno_local = false;
		}
	}

//--------------------------------------------------------------------------------------------

	function conexion(){
        #- Busco el camino a los documentos de imagen del cidig
		$valor=false;
		if(substr(toba_dir(),0,2) == 'D:'){  //ó C:
			$valor= true;
		}
		return $valor;
	}
//--------------------------------------------------------------------------------------------

	function get_conexion_ssh2()
	{
		$sftp = null;
		if(!($conexion_ssh = ssh2_connect('192.168.10.200', 22))){
			toba::notificacion()->vaciar();
			toba::notificacion()->set_titulo('Sistema de STAF MCC');
			toba::notificacion()->agregar('ATENCION: Ha fallado la conexión SSH con el servidor de Desarrollo.
				<br> Las imágenes no se descargarán apropiadamente.');
		}else{
			ssh2_auth_password($conexion_ssh, 'root', 'roda1950');
			$sftp = ssh2_sftp($conexion_ssh);
		}
		return $sftp;
	}

     #------Genera una subcarpeta concatenando ST (de STAF) con el AÑOMES
	function get_sub_carpeta()
	{
		return "st".date('Ym').'/';
	} 
     #------Crea el nombre de pdf usando el numero del acta
	function get_nombre_foto($nro_acta)
	{
		$dev = "ac_".$nro_acta.".pdf";
		return $dev;
	} 
	#------ #- Analizo si la subcarpeta destino existe. Si no, la crea y otorga los permisos correspondientes
	function existe_carpeta()
	{
		$rutacarpeta='';
         
		if($this->s__entorno_local){
			$sftp = $this->get_conexion_ssh2();
			$carpeta_existente='ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$this->get_sub_carpeta();
			$carpeta_existe = file_exists($carpeta_existente);
		}else{
			$carpeta_existente=$this->s__carpeta_imagenes_absoluto.$this->get_sub_carpeta();
			$carpeta_existe = file_exists($carpeta_existente);
		}
		$carpeta_auxiliar=explode('/',$carpeta_existente);
		$carpeta_auxiliar2='/';
		$cant=count($carpeta_auxiliar);
		$bandera=0;
		for ($i=0; $i < $cant; $i++) { 
			if (($carpeta_auxiliar[$i] == 'var') ) {
				
				$bandera=$i;
				
			}
		}
			for ($i=$bandera; $i < $cant-1; $i++) { 
				$carpeta_auxiliar2= $carpeta_auxiliar2.$carpeta_auxiliar[$i].'/';
				if(!$carpeta_existe){
					if($this->s__entorno_local){
						mkdir('ssh2.sftp://'.$sftp.$carpeta_auxiliar2);
						chmod('ssh2.sftp://'.$sftp.$carpeta_auxiliar2, 0777);
						
					}else{
						mkdir($carpeta_auxiliar2);
						chmod($carpeta_auxiliar2, 0777);
					
					}
				}
							
			
		}
		
		//$rutacarpeta=$this->s__carpeta_imagenes_absoluto.$this->get_sub_carpeta();
		//return $rutacarpeta;
		
		return $carpeta_auxiliar2;
		
	}
	//----------------------------------------------------------------------------------------------------------/
   
   //------------------------------------------------------------------------------------------------------------------
   #------ #- Analizo si la carpeta para hacer respaldos existe. Si no, la crea y otorga los permisos correspondientes
	function existe_carpeta_respaldo()
	{
		$rutacarpeta='';
		$respaldo = 'respaldo/';
         
		if($this->s__entorno_local){
			$sftp = $this->get_conexion_ssh2();
			$carpeta_existe = file_exists('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$respaldo);
		}else{
			$carpeta_existe = file_exists($this->s__carpeta_imagenes_absoluto.$respaldo);
		}
		if(!$carpeta_existe){
			if($this->s__entorno_local){
				chmod('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto, 0777);
				mkdir('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$respaldo);
				chmod('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$respaldo, 0777);
			}else{
				mkdir($this->s__carpeta_imagenes_absoluto.$respaldo);
				chmod($this->s__carpeta_imagenes_absoluto.$respaldo, 0777);
			}
		}
		$rutacarpeta=$this->s__carpeta_imagenes_absoluto.$respaldo;
		return $rutacarpeta;
	}
   //------------------------------------------------------------------------------------------------------------------
  #------ #- Analizo si la subcarpeta del archivo borrado o modificado existe. Si no, la crea y otorga los permisos correspondientes
	function existe_carpeta_vieja($datos)
	{
		$datos=explode('/',$datos);
		$rutacarpeta='';
         #- Analizo si la subcarpeta destino existe.
		if($this->s__entorno_local){
			$sftp = $this->get_conexion_ssh2();
			$carpeta_existe = file_exists('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$datos[0]);
		}
		else{
			$carpeta_existe = file_exists($this->s__carpeta_imagenes_absoluto.$datos[0]);
		}

		if(!$carpeta_existe){
			if($this->s__entorno_local){

				chmod('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto, 0777);

				mkdir('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$datos[0]);

				chmod('ssh2.sftp://'.$sftp.$this->s__carpeta_imagenes_absoluto.$datos[0],0777);
			}
			else{
				mkdir($this->s__carpeta_imagenes_absoluto.$datos[0]);
				chmod($this->s__carpeta_imagenes_absoluto.$datos[0],0777);
			}
		}
		$rutacarpeta=$this->s__carpeta_imagenes_absoluto.$datos[0].'/';
		return $rutacarpeta;
	}
  #-----------------------------------------------------------------------------------------------------------------#
  #------Metodo para 
	function modificar_foto($datos)
	{
         //cargo una imagen
		if($this->s__entorno_local){
			$sftp=$this->get_conexion_ssh2();
			$carpeta=$this->existe_carpeta();
			$auxiliar=$this->get_nombre_foto($datos['ac_nro']);
			if ($datos['nombre_imagen_up']['tmp_name']){
				$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'], 'ssh2.sftp://'.$sftp.$carpeta.$auxiliar);
				if ($check){
                    //toba::notificacion()->warning('Cargo');
				}else{
                   // toba::notificacion()->warning('Error - No se cargo la imagen');
				}
			}
        // fin cargo imagen
		}else{
			$carpeta=$this->existe_carpeta();
			$auxiliar=$this->get_nombre_foto($datos['ac_nro']);
			if ($datos['nombre_imagen_up']['tmp_name']){
				$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'],$carpeta.$auxiliar);
				if ($check){
                       // toba::notificacion()->warning('Cargo');
				}else{
                       // toba::notificacion()->warning('Error - No se cargo la imagen');
				}
			}
        // fin cargo imagen
		}            
	}
    #------


	function borrar_foto($datos)
	{
        //borrar una imagen
		$nombre_completo=explode('/',$datos);
		$carpeta=$this->existe_carpeta_vieja($nombre_completo[0]);

		if ($this->s__entorno_local) {
			$sftp=$this->get_conexion_ssh2();
			$check=unlink('ssh2.sftp://'.$sftp.$carpeta.$nombre_completo[1]);

			if ($check){
                //toba::notificacion()->warning('se borro');
			}else{
                //toba::notificacion()->warning('Error - No se borro la imagen');
			}
		}else{
			$check=unlink($carpeta.$nombre_completo[1]);
			if ($check){
                //toba::notificacion()->warning('se borro');
			}else{
                //toba::notificacion()->warning('Error - No se borro la imagen');
			}
		}

	}
    #------


	function renombrar_foto($datos)
	{
		$nombre=explode('/',$datos);
		$carpeta_vieja=$this->existe_carpeta_vieja($nombre[0]);
		$imagen_vieja=$nombre[4];
		$imagen_nueva=$nombre[1];

        //renombrar el archivo con el nuevo nombre

		if ($this->s__entorno_local) {
			$sftp=$this->get_conexion_ssh2();
			ssh2_sftp_rename($sftp, $carpeta_vieja.$imagen_nueva, $carpeta_vieja.$imagen_vieja);
		} else{
			rename($carpeta_vieja.$imagen_nueva, $carpeta_vieja.$imagen_vieja);
		}
	}
//---------------------------------------------------------------------------------------------
	function agregar_foto($datos){
        //cargo una imagen
        $nombre_completo = explode('/',$datos);
		$carpeta_respaldo = $this->existe_carpeta_respaldo().$nombre_completo[1]; 
		$carpeta_nueva = $this->existe_carpeta_vieja($datos).$nombre_completo[1];
		if ($this->s__entorno_local) {
			$sftp=$this->get_conexion_ssh2();
			copy('ssh2.sftp://'.$sftp.$carpeta_respaldo,'ssh2.sftp://'.$sftp.$carpeta_nueva);
			unlink('ssh2.sftp://'.$sftp.$carpeta_respaldo);
		}else{
			copy($carpeta_respaldo,$carpeta_nueva);
			unlink($carpeta_respaldo);
		}
	}
//--------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------
//-------------------*****METODOS PARA ACCIONES****-------------------------------------------

//---------------------------------------------------------------------------------------------
function recuperar_foto_acciones($datos){
    //cargo una imagen
    $nombre_completo = explode('/',$datos);
    $carpeta_respaldo = $this->existe_carpeta_respaldo().$nombre_completo[1]; 
    $carpeta_nueva = $this->existe_carpeta_vieja($datos).$nombre_completo[1];
    if ($this->s__entorno_local) {
        $sftp=$this->get_conexion_ssh2();
        copy('ssh2.sftp://'.$sftp.$carpeta_respaldo,'ssh2.sftp://'.$sftp.$carpeta_nueva);
        unlink('ssh2.sftp://'.$sftp.$carpeta_respaldo);
    }else{
        copy($carpeta_respaldo,$carpeta_nueva);
        unlink($carpeta_respaldo);
    }
}

   //------------------------------------------------------------------------------------------------------------------

function get_nombre_foto_acciones($carpeta)
{
    $flags=0;
    $numero_ramdom=rand(1000, 60000);
    $dev = "act_".$numero_ramdom.".pdf";
    $carpdev=$carpeta."/".$dev;
    while ($flags <= 0) {
        $rs = toba::db()->consultar("Select 2 From tribunal.imagenes_acciones Where nombre_imagen= '".$carpdev."'"); 
        if ($rs == 2) {
            $numero_ramdom=rand(1000, 60000);
            $dev = "act_".$numero_ramdom.".pdf";
            $carpdev=$carpeta."/".$dev;
            }else{
                $flags=1;
            }
        }
        return $dev;
} 




#----------------------------------------Metodo para 
function modificar_foto_acciones($datos)
{
    $carpeta=$this->existe_carpeta();
    $auxiliar=$this->get_nombre_foto_acciones($carpeta);
   // $datos['nombre_imagen']= $carpeta.$auxiliar;
     //cargo una imagen
    if($this->s__entorno_local){
        $sftp=$this->get_conexion_ssh2();    
       if ($datos['nombre_imagen_up']['tmp_name']){
            $check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'], 'ssh2.sftp://'.$sftp.$carpeta.$auxiliar);
            if ($check){
                toba::notificacion()->warning('Cargo');
            }else{
               toba::notificacion()->warning('Error - No se cargo la imagen');
            }
        }
    // fin cargo imagen
    }else{       
      
        if ($datos['nombre_imagen_up']['tmp_name']){
            $check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'],$carpeta.$auxiliar);
            if ($check){
                toba::notificacion()->warning('Cargo');
            }else{
                toba::notificacion()->warning('Error - No se cargo la imagen');
            }
        }
    // fin cargo imagen
    }
    $carpeta = explode('st', $carpeta);
    $carpeta = 'st'.$carpeta[2].$auxiliar;
    return   $carpeta;          
}
//------------------------------------------------------------------------------------------------------------------

#-----------------------------------------------------------------------------------------------------------------#
function borrar_foto_acciones($datos)
	{
        //borrar una imagen
		$nombre_completo=explode('/',$datos);
		$carpeta=$this->existe_carpeta_vieja($nombre_completo[0]);

		if ($this->s__entorno_local) {
			$sftp=$this->get_conexion_ssh2();
           	$check=unlink('ssh2.sftp://'.$sftp.$carpeta.$nombre_completo[1]);

			if ($check){
                toba::notificacion()->warning('se borro');
			}else{
                toba::notificacion()->warning('Error - No se borro la imagen');
			}
		}else{
			$check=unlink($carpeta.$nombre_completo[1]);
			if ($check){
                toba::notificacion()->warning('se borro');
			}else{
                toba::notificacion()->warning('Error - No se borro la imagen');
			}
		}

	}
    #------
    #-----------------------------------------------------------------------------------------------------------------#
function salvar_foto_acciones($datos)
{
    //borrar una imagen
    $nombre_completo=explode('/',$datos);
    $carpeta_respaldo = $this->existe_carpeta_respaldo().$nombre_completo[1]; 
    $carpeta_nueva = $this->existe_carpeta_vieja($datos);
    if ($this->s__entorno_local) {
        $sftp=$this->get_conexion_ssh2();
        $check=  copy('ssh2.sftp://'.$sftp.$carpeta_nueva,'ssh2.sftp://'.$sftp.$carpeta_respaldo);
        unlink('ssh2.sftp://'.$sftp.$carpeta_nueva.$nombre_completo[1]);

    }else{
        $check= copy($carpeta_nueva,$carpeta_respaldo);
        unlink($carpeta_nueva.$nombre_completo[1]);
        
    }
    for ($i=0; $i < 999; $i++) { //cargo mi array de imagenes
        if(is_null($this->s__img_tratar[$i])){
            $this->s__img_salvadas[$i]['nombre'] = $carpeta_respaldo;
            break;
        }                      
    }
    

}
#------
//-------------------****FIN METODOS PARA ACCIONES****----------------------------------------
//---------------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
//------------------****** FORMULARIO EXPEDIENTE*****-------------------------------------------
//----------------------------------------------------------------------------------------------

	function conf__frm_expediente(staf_ei_formulario $form)
	{
		/** codigo del ayuda ----------------------------------------------------------------- */
		$id_objeto = $this->pantalla($id_pantalla)->get_id();
		foreach(toba::usuario()->get_perfiles_funcionales() as $valor)
		{
			$perfiles[] = "'".utf8_encode($valor)."'"; //lista los perfiles de usuarios separados por coma
		}

		$restricciones = trim(implode(toba::usuario()->get_restricciones_funcionales(),','),','); //lista las restricciones func. separados por coma

		$parametros_ayuda = array('proyecto'=>utf8_encode($id_objeto[0]),
			'objeto'=>$id_objeto[1],
			'descr_objeto'=>utf8_encode('pantalla: Areas que solucionan problemas (no valido como parametro)')
			/*,'p1_contenido'=>utf8_encode('tipo de inspector')
			,'p1'=>utf8_encode($this->s__inspector['id_insp_tipo'])
			,'p2_contenido'=>utf8_encode('es supervisor')
			,'p2'=>utf8_encode($this->s__inspector['es_supervisor'])
			,'p3_contenido'=>utf8_encode('Id. Pantalla')
			,'p3'=>utf8_encode($id_pantalla)*/
			,'p4_contenido'=>utf8_encode('Perfiles del usuario')
			,'p4'=>trim(implode($perfiles,','),',')
			,'p5_contenido'=>utf8_encode('Restricciones funcionales')
			,'p5'=>utf8_encode($restricciones)
		);

		/*   if(in_array('obras_supervisor',toba::usuario()->get_perfiles_funcionales()) or in_array('admin',toba::usuario()->get_perfiles_funcionales()))
			{
				$parametros_ayuda['p2_contenido'] = utf8_encode('Es administrador');
				$parametros_ayuda['p2'] = utf8_encode('1');
			}*/

			$this->evento('ayuda')->vinculo()->set_parametros($parametros_ayuda);
			/** --------------------------------------------------------------------------------------------------------------------------------------- **/

		if($this->get_relacion()->tabla('expediente')->hay_cursor()) //seleccion
		{
			$datos = $this->get_relacion()->tabla('expediente')->get();
			$tribu=$datos['id_tribunal'];
			$sql = toba::db()->consultar("select c01depresu from tribunal.tribunales where id_tribunal=".$tribu);
			$this->s__tribunal_dependencia=$sql[0]["c01depresu"];
			$this->s__idExpe = $datos['id_expediente'];
			$this->s__usuAlta = $datos['usu_alta'];
			$this->s__usuMod = $datos['usu_mod'];
			$this->s__fechaMod = $datos['fe_mod'];
			$this->s__numExpe = $datos['n_expediente'];


//------------------------------------------- USUARIOS -----------------------------------------------------------------
//---------------------------- Lo q los usuarios pueden ver del form Expediente ----------------------------------------
			$this->s__usuario = consultas::get_pf();
			// if($this->s__usuario == 'supervisor')
			// {
				if(!$this->get_relacion()->tabla('expediente')->esta_cargada()) // si no existe el nro de exp, no se ponen los campos en solo lectura
				{
					$form->ef('id_prioridad')->set_solo_lectura(false);
				}
				else // si existe se ponen los campos en solo lectura
				{
					$form->ef('id_prioridad')->set_solo_lectura();
				}
			// }
			// elseif($this->s__usuario == 'carga' || $this->s__usuario == 'carga_juzgado')
			// {
			// 	if(!$this->get_relacion()->tabla('expediente')->esta_cargada()) // si no existe el nro de exp, no se ponen los campos en solo lectura
			// 	{
			// 		$this->dep('frm_expediente')->set_solo_lectura(array('id_prioridad','id_tipo_persona','identidad_verif','c05tipodoc','nrodoc',
			// 			'nrocuit','sexo','apyn','domicilio'),false);

			// 	}
			// 	// else // si existe se ponen los campos en solo lectura
			// 	// {
			// 	// 	$this->dep('frm_expediente')->set_solo_lectura(array('id_prioridad','id_tipo_persona','identidad_verif','c05tipodoc','nrodoc',
			// 	// 		'nrocuit','sexo','apyn','domicilio'));

			// 	// }
			// }

//--------------------------------------------------------------------------------------------

			$datos['n_expediente'] = substr($datos['n_expediente'],0,4).'-'.substr($datos['n_expediente'],4,2).'-'.substr($datos['n_expediente'],6,2).'-'.substr($datos['n_expediente'],8,8); /** Modifique 14/12 */
		
			if(isset($datos['id_tribunal'])) // detecta si el tribunal existe o no
			{
				$sql = "Select id_tribunal, descripcion From tribunal.tribunales where id_tribunal = {$datos['id_tribunal']}";
				$rs = toba::db()->consultar($sql);
				if(count($rs) > 0)
				{
					$datos['descripcion'] = $rs[0]['descripcion'];
				}
			}
			//---- Doy vuelta la fecha
			$datos['fe_alta']=date("d-m-Y",strtotime($datos['fe_alta']));
			$datos['fe_mod']=date("d-m-Y",strtotime($datos['fe_mod']));

			//--- Defino si es un CUIT o DNI y lo muestro en el Formulario --------
			if(strlen($datos['nrodoc']) == 11)
			{
				$datos['nrocuit'] = substr($datos['nrodoc'],0,2).substr($datos['nrodoc'],2,8).substr($datos['nrodoc'],10,1);
			}
			elseif(strlen($datos['nrodoc']) == 10)
			{
				$datos['nrocuit'] = substr($datos['nrodoc'],0,2).'0'.substr($datos['nrodoc'],2,7).substr($datos['nrodoc'],9,1);
			}

			//---- Se utiliza para armar la seleccion en cascada------------------------------------------
			//---- Se guarda el dato del tipo de infraccion en memoria, para luego determinar los tramites
			if($datos['c06id'] != '')
			{
				toba::memoria()->set_dato('provincia',$datos['c06id']);
			}
			//--------------------------------------------------------------------------------------------


			$form->set_datos($datos);
			$form->ef('c07id')->vinculo()->agregar_parametro('provincia',$datos['c06id']);
		}
		else //nuevo registro
		{
			$firma = toba::usuario()->get_id();
			$form->ef('usu_alta')->set_estado($firma);
			$form->ef('usu_alta')->set_solo_lectura();

			$fecha_actual = date('d-m-Y'); //devuelve la fecha completa
			$form->ef('fe_alta')->set_estado($fecha_actual); //muestro la fecha actual en el campo fecha de alta
			$form->ef('fe_alta')->set_solo_lectura();

		}
		 if(isset($datos[0]['c06id'])){
                    $this->s__tt_provincia = $datos[0]['c06id'];
                  }

		
	}

	function evt__frm_expediente__modificacion($datos) //-- Evento Implícito
	{
		if($this->get_relacion()->tabla('expediente')->hay_cursor()) //seleccion
		{
			$datos['usu_mod'] = toba::usuario()->get_id();
			$datos['fe_mod'] = date('d-m-Y');
			$datos['apyn'] = mb_convert_case($datos['apyn'], MB_CASE_UPPER, "LATIN1");
			$datos['domicilio'] = mb_convert_case($datos['domicilio'], MB_CASE_UPPER, "LATIN1");

			//-----Asigno los códigos del dni y cuil ----------
			if($datos['c05tipodoc'] == "DNI")
			{
				$datos['c05tipodoc'] = '5';
			}
			elseif($datos['c05tipodoc'] == "CUIL/CUIT")
			{
				$datos['c05tipodoc'] = '6';
			}


			if($datos['nrodoc'] == '' or $datos['nrodoc'] == 0)
			{
				if($datos['nrocuit'] != '')
				{
					$datos['nrodoc'] = $datos['nrocuit'];
				}else{
					$datos['nrodoc'] =  0;
				}
			}

			//----- le quito los guiones al nro de expediente para q impacte sin guiones en la tabla -----
			$exp = explode('-', $datos['n_expediente']);
			$datos['n_expediente'] = $exp[0].$exp[1].$exp[2].$exp[3];

			//-- Guardo la prioridad, posiblemente modificada, del Expediente
			$this->s__distintop = false;
			$this->s__prinew = $datos['id_prioridad'];
			if($this->s__prinew != $this->controlador()->get_prioridad_old())
			{
				if($this->controlador()->get_prioridad_old() == 2)
				{
					$this->get_relacion()->tabla('acta')->set_columna_valor('id_tramite', null); //-- cuando se modifica la prioridad me blanquea el tramite ya q si es urgente y cambia x comun,no deberia tener un tramite
					$this->get_relacion()->tabla('acta')->set_columna_valor('secuestro',null);
				}
				$this->s__distintop = 'true';
			}
			$this->get_relacion()->tabla('expediente')->set($datos);
		}
		else //nuevo registro
		{
			$datos['apyn'] = mb_convert_case($datos['apyn'], MB_CASE_UPPER, "LATIN1");
			$datos['domicilio'] = mb_convert_case($datos['domicilio'], MB_CASE_UPPER, "LATIN1");

			//-----Asigno los códigos del dni y cuil
			if($datos['c05tipodoc'] == "DNI")
			{
				$datos['c05tipodoc'] = '5';
			}
			elseif($datos['c05tipodoc'] == "CUIL/CUIT")
			{
				$datos['c05tipodoc'] = '6';
			}
			$datos['usu_mod'] = ' ';

			if($datos['nrodoc'] == '')
			{
				if($datos['nrocuit'] != '')
				{
					$datos['nrodoc'] = $datos['nrocuit'];
				}else{
					$datos['nrodoc'] = 0;
				}
			}
			$cursor = $this->get_relacion()->tabla('expediente')->nueva_fila($datos);
			$this->get_relacion()->tabla('expediente')->set_cursor($cursor);
			
		}

	}

//---------------------------------------------------------------------------------------------------------------
//-----------------------------****** PANTALLA ACTAS *****-------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------

	function conf__pant_actas(toba_ei_pantalla $pantalla)
	{
		// if($this->s__usuario <> 'superusuario')
		// {
		// 	$pantalla->eliminar_dep('frm_motivo_resorteo');
		// }
		// if($this->s__usuario == 'carga' || $this->s__usuario == 'carga_juzgado')
		// {
		// 	if($this->get_relacion()->tabla('expediente')->esta_cargada())
		// 	{
		// 		$pantalla->eliminar_dep('frm_actas');
		// 	}
		// }
	}

//---------------------------------------------------------------------------------------------------------------
//-----------------------------****** CUADRO DE ACTAS *****------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------

	function conf__cd_actas(staf_ei_cuadro $cuadro)
	{
		/** codigo del ayuda ---------------------------------------------------------------------------------- */
		$id_objeto = $this->pantalla($id_pantalla)->get_id();
		foreach(toba::usuario()->get_perfiles_funcionales() as $valor)
		{
			$perfiles[] = "'".utf8_encode($valor)."'"; //lista los perfiles de usuarios separados por coma
		}

		$restricciones = trim(implode(toba::usuario()->get_restricciones_funcionales(),','),','); //lista las restricciones func. separados por coma

		$parametros_ayuda = array('proyecto'=>utf8_encode($id_objeto[0]),
			'objeto'=>$id_objeto[1],
			'descr_objeto'=>utf8_encode('pantalla: Areas que solucionan problemas (no valido como parametro)')
			/*,'p1_contenido'=>utf8_encode('tipo de inspector')
			,'p1'=>utf8_encode($this->s__inspector['id_insp_tipo'])
			,'p2_contenido'=>utf8_encode('es supervisor')
			,'p2'=>utf8_encode($this->s__inspector['es_supervisor'])
			,'p3_contenido'=>utf8_encode('Id. Pantalla')
			,'p3'=>utf8_encode($id_pantalla)*/
			,'p4_contenido'=>utf8_encode('Perfiles del usuario')
			,'p4'=>trim(implode($perfiles,','),',')
			,'p5_contenido'=>utf8_encode('Restricciones funcionales')
			,'p5'=>utf8_encode($restricciones)
		);

		/*   if(in_array('obras_supervisor',toba::usuario()->get_perfiles_funcionales()) or in_array('admin',toba::usuario()->get_perfiles_funcionales()))
			{
				$parametros_ayuda['p2_contenido'] = utf8_encode('Es administrador');
				$parametros_ayuda['p2'] = utf8_encode('1');
			}*/

			$this->evento('ayuda')->vinculo()->set_parametros($parametros_ayuda);
			/** --------------------------------------------------------------------------------------------------------------------------------------- **/

			$dt_cd = $this->get_relacion()->tabla('acta')->get_filas();
		    $dt_cd =rs_ordenar_por_columna($dt_cd, array('id_acta'), SORT_ASC);//--- ordeno mi array por el id ---

		if(count($dt_cd) > 0) //-- hay actas cargadas ---
		{
			$this->s__nro_acta = 'existe';
			$tram = 0;
			foreach($dt_cd as $i => $fila)
			{
				if($dt_cd[$i]['id_tramite'] == '')
				{
					$sql = "Select
					(Select descripcion From tribunal.medio_constatacion where id_medio= '".$fila['id_medio']."') as medio,
					(Select descripcion From tribunal.tipo_infraccion where id_tipoi= '".$fila['id_tipoi']."') as tipo";
				}else{
					$sql = "Select
					(Select descripcion From tribunal.tramites where id_tramite= '".$fila['id_tramite']."') as desc_tram,
					(Select descripcion From tribunal.medio_constatacion where id_medio= '".$fila['id_medio']."') as medio,
					(Select descripcion From tribunal.tipo_infraccion where id_tipoi= '".$fila['id_tipoi']."') as tipo";
				}
				$rs = toba::db()->consultar($sql);

				if(count($rs) > 0)
				{
					//---Armo el Acta para que lo muestre en el cuadro
					if($dt_cd[$i]['id_tipoi'] == '00')//*********************************************************************
					{
						$dt_cd[$i]['acta_completa'] = substr($dt_cd[$i]['acta_completa'],0,2).'-'.substr($dt_cd[$i]['acta_completa'],2,2).'-'.substr($dt_cd[$i]['acta_completa'],4,3).'-'.substr($dt_cd[$i]['acta_completa'],7,2).'-'.substr($dt_cd[$i]['acta_completa'],9,8); /*** modifique 14/12 */
					}else{
						$dt_cd[$i]['acta_completa'] = substr($dt_cd[$i]['acta_completa'],0,4).'-'.substr($dt_cd[$i]['acta_completa'],4,2).'-'.substr($dt_cd[$i]['acta_completa'],6,8); /*** modifique 14/12 */
					}

					$dt_cd[$i]['tipo'] = $rs[0]['tipo'];
					$dt_cd[$i]['medio'] = $rs[0]['medio'];
					$dt_cd[$i]['desc_tram'] = $rs[0]['desc_tram'];

					//--------Verifica si existe un tramite --------
					if($dt_cd[$i]['id_tramite'] != '')
					{
						$tram += 1;
					}
				}
			}
			$prioridad = $this->get_relacion()->tabla('expediente')->get_columna('id_prioridad');

			//--------Recupero el dato de la memoria
			toba::memoria()->set_dato('prioridad',$this->s__prinew);

			if($tram > 0)
			{
				$this->s__tramite = 'existe';
				if($prioridad == 1)
				{
					toba::notificacion()->warning('La Prioridad debe ser urgente');
				}

			}else{
				$this->s__tramite = 'no_existe';
				if($prioridad == 2)
				{
					toba::notificacion()->warning('Debe agregar un Tr�mite');
				}
			}

//-------------------------------------------- USUARIOS ----------------------------------------------
//-------------si el Perfil es Carga no puede ni agregar, ni modificar, ni guardar nada --------------
			// if($this->s__usuario == 'supervisor')
			// {
			// 	if($this->get_relacion()->tabla('expediente')->esta_cargada())
			// 	{
			// 		$this->dep('frm_actas')->ef('id_tipoi')->set_estado($dt_cd[0]['id_tipoi']);
			// 		$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(true);
			// 	}else{
			// 		if(count($this->get_relacion()->tabla('acta')->get_filas()) > 0)
			// 		{
			// 			if(!$this->get_relacion()->tabla('acta')->hay_cursor())
			// 			{
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_estado($dt_cd[0]['id_tipoi']);
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura();
			// 			}else{
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(false);
			// 			}
			// 		}else{
			// 			$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(false);
			// 		}
			// 	}
			// }
			// elseif($this->s__usuario == 'carga' || $this->s__usuario == 'carga_juzgado')
			// {
			// 	$this->dep('frm_actas')->ef('id_tipoi')->set_estado($dt_cd[0]['id_tipoi']);
			// 	$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(true);
			// 	if($this->get_relacion()->tabla('expediente')->esta_cargada())
			// 	{
			// 		$cuadro->evento('seleccion')->ocultar();
			// 		$this->dep('frm_actas')->evento('alta')->ocultar();
			// 		$this->evento('procesar')->ocultar();
			// 	}else{
			// 		if(count($this->get_relacion()->tabla('acta')->get_filas()) > 0)
			// 		{
			// 			if(!$this->get_relacion()->tabla('acta')->hay_cursor())
			// 			{
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_estado($dt_cd[0]['id_tipoi']);
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura();
			// 			}else{
			// 				$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(false);
			// 			}
			// 		}else{
			// 			$this->dep('frm_actas')->ef('id_tipoi')->set_solo_lectura(false);
			// 		}
			// 	}
			// }

//-----------------------------------------------------------------------------------------------------------------------

//----- Cuando es Sinai; el tramite, el dominio, y el nro. de secuestro, debe ser el mismo p todas las actas -----------
			if($dt_cd[0]['id_tipoi'] == '00')
			{
				$this->dep('frm_actas')->ef('id_tramite')->set_estado($dt_cd[0]['id_tramite']);
				//$this->dep('frm_actas')->ef('id_tramite')->deshabilitar();
				$this->dep('frm_actas')->ef('secuestro')->set_estado($dt_cd[0]['secuestro']);
				//$this->dep('frm_actas')->ef('secuestro')->set_solo_lectura();
				$this->dep('frm_actas')->ef('dominio')->set_estado($dt_cd[0]['dominio']);
				//$this->dep('frm_actas')->ef('dominio')->set_solo_lectura();
				if($this->dep('frm_actas')->ef('secuestro')->get_estado() == null){
					$this->dep('frm_actas')->ef('secuestro')->set_solo_lectura(false);
				}
				if($this->dep('frm_actas')->ef('dominio')->get_estado() == ''){
					$this->dep('frm_actas')->ef('dominio')->set_solo_lectura(false);
				}
			}
		}
		else{
			$this->s__nro_acta = null;
		}


		#- Agrego al cuadro columna (no visible) con el nombre de la imagen
		$hay_cursor = false;
		if($this->get_relacion()->tabla('acta')->hay_cursor()){
			$old_cursor = $this->get_relacion()->tabla('acta')->get_cursor();
			$hay_cursor = true;
		}
		foreach ($dt_cd as $key => $fila) {
			$this->get_relacion()->tabla('acta')->set_cursor($fila['x_dbr_clave']);
			$imagenes = $this->get_relacion()->tabla('imagenes_actas')->get_filas();
			if(count($imagenes)>0){
				$dt_cd[$key]['nombre_imagen'] = $imagenes[0]['nombre_imagen'];
			}else{
				$dt_cd[$key]['nombre_imagen'] = null;
			}
		}
		if($hay_cursor){
			$this->get_relacion()->tabla('acta')->set_cursor($old_cursor);
		}else{
			$this->get_relacion()->tabla('acta')->resetear_cursor();
		}



		$cuadro->set_datos($dt_cd);
	}

	function evt__cd_actas__seleccion($seleccion)
	{
		$this->get_relacion()->tabla('acta')->set_cursor($seleccion);
		$this->s__fila_acta = $this->get_relacion()->tabla('acta')->get();

		#- Ubico el cursor en la tabla de imágenes. Esto se hace de esta manera dado que, por el momento, sólo puede existir un registro de imágenes por Acta
		if($this->get_relacion()->tabla('imagenes_actas')->get_cantidad_filas()){
			$cursores = $this->get_relacion()->tabla('imagenes_actas')->get_filas()[0]['x_dbr_clave'];
			$this->get_relacion()->tabla('imagenes_actas')->set_cursor($cursores);
			$this->s__fila_imagen = $this->get_relacion()->tabla('imagenes_actas')->get();
            //$cursores = $this->get_relacion()->tabla('imagenes')->get_id_filas();
            // $this->get_relacion()->tabla('imagenes')->set_cursor($cursores[0]);



		}
	}


//---------------------------------------------------------------------------------------------------------------
//-----------------------------****** FORMULARIO DEL ACTA *****--------------------------------------------------
//---------------------------------------------------------------------------------------------------------------

	function conf__frm_actas(staf_ei_formulario $form)
	{
		if($this->get_relacion()->tabla('acta')->hay_cursor())
		{

			$datos = $this->get_relacion()->tabla('acta')->get();


			$datos['fe_alta']=date("d-m-Y",strtotime($datos['fe_alta']));
			$datos['fe_mod']=date("d-m-Y",strtotime($datos['fe_mod']));
			$id_acta= $datos['id_acta'];

			if($this->get_relacion()->tabla('imagenes_actas')->hay_cursor()){
				$reg = $this->get_relacion()->tabla('imagenes_actas')->get();
				$datos['nombre_imagen'] = $reg['nombre_imagen'];
				$datos['descripcion'] = $reg['descripcion'];

			}

			$form->set_datos($datos);
			$conexion=$this->conexion();
			$nombreimagen=$this->get_relacion()->tabla('imagenes_actas')->get_columna('nombre_imagen');
			$nombreimagen=explode('/',$nombreimagen);
			if ($this->s__entorno_local) {

				$conexion="http://desarrollo.ciudaddecorrientes.gov.ar";
				$form->ef('ver_imagen')->set_estado("<embed src='".$conexion.$this->s__carpeta_imagenes.$nombreimagen[0].'/'.$nombreimagen[1].
					"' type='application/pdf' height='300' width='500'>");
			}
			else
			{
				$form->ef('ver_imagen')->set_estado("<embed src='".$this->s__carpeta_imagenes.$nombreimagen[0].'/'.
					$nombreimagen[1].
					"' type='application/pdf' height='300' width='500'>");

			}


			if (!is_null($id_acta)) {
                //---- Se utiliza para armar la seleccion en cascada------
                //---- Se guarda el dato del tipo de infraccion en memoria, para luego determinar los tramites
				if($datos['id_tipoi'] != ''){
					toba::memoria()->set_dato('tipo_inf',$datos['id_tipoi']);
				}
				else{
					$form->ef('ac_anio')->set_estado(date('Y'));
				}
			}
		}  

	}


	function evt__frm_actas__alta($datos)
	{        

        //----------------------- determino la longitud del n° de acta ingresado ----------------------------
		$con = strlen($datos['ac_nro']);
		if($con < 8)  /********************** Modifique 14/12 */
		{
        // si es menor a 8 digitos, lo completa con 0.
			$datos['ac_nro']= str_pad($datos['ac_nro'],8,'0',STR_PAD_LEFT);
			$acta_vieja= str_pad($datos['ac_nro'],6,'0',STR_PAD_LEFT);
		}
        $datos['dominio'] =preg_replace('[\s+]',"", $datos['dominio']);//---- le quito los espacios de la cadena enviada

		//----------------------------------------- Fin ------------------------------------------------------
        if($datos['id_tipoi'] == '00')
        {
        	$datos['acta_completa'] = $datos['id_medio'].$datos['id_provincia'].$datos['id_municipio'].substr($datos['ac_anio'],2,2).$datos['ac_nro'];
        	$acta_vieja_completa = $datos['id_medio'].$datos['id_provincia'].$datos['id_municipio'].substr($datos['ac_anio'],2,2).$acta_vieja;
        	$datos['dominio'] = mb_convert_case($datos['dominio'], MB_CASE_UPPER, "LATIN1");
        }else{
        	$datos['acta_completa'] = $datos['ac_anio'].$datos['id_tipoi'].$datos['ac_nro'];
        	$acta_vieja_completa = $datos['ac_anio'].$datos['id_tipoi'].$acta_vieja;
        	$datos['dominio'] = mb_convert_case($datos['dominio'], MB_CASE_UPPER, "LATIN1");
        }
        $datos['usu_alta'] = toba::usuario()->get_id();
        $datos['usu_mod'] = ' ';
        $datos['fe_alta'] = date('d-m-Y');

        $this->s__infnew = $datos['id_tipoi'];
		//--------------------- Cambio de Tipo de Infracción y Blanqueo el Trámite -----------------------------
		//------------------ Si se modifica el TI, se modifica todos los TI anteriores -------------------------
		//------------------ Todos los Trámites de las Actas anteriores se blanquean ---------------------------
        $d_actas = $this->get_relacion()->tabla('acta')->get_filas();

        if($datos['id_tipoi'] != $d_actas[0]['id_tipoi'])
        {
        	foreach($d_actas as $i => $filas)
        	{
        		$con = strlen($d_actas[$i]['ac_nro']);
        		if($con < 8)    /********************** Modifique 14/12 */
        		{
                    // si es menor a 8 digitos, lo completa con 0.
        			$d_actas[$i]['ac_nro']= str_pad($d_actas[$i]['ac_nro'],8,'0',STR_PAD_LEFT);
        		}

                if($datos['id_tipoi'] == '21')// Tipo de Inf: Foto Multa //
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
                    $this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', null); // blanquea el nro. de secuestro de todas las filas q estan en el DT //
                    $datos['secuestro'] = null; // blanquea el numero de secuestro del registro q se esta modificando //
                }
                elseif(($datos['id_tipoi'] >= '03') and ($datos['id_tipoi'] < '21'))
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', null);
                    $this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', null); // blanquea el nro. de secuestro de todas las filas q estan en el DT //
                    $datos['secuestro'] = null; // blanquea el numero de secuestro del registro q se esta modificando //
                }
                elseif(($datos['id_tipoi'] == '01') or ($datos['id_tipoi'] == '02'))
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                }
                elseif($datos['id_tipoi'] == '00') // Tipo de Inf: Sinai //
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['id_medio'].$d_actas[$i]['id_provincia'].$d_actas[$i]['id_municipio'].substr($d_actas[$i]['ac_anio'],2,2).$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', $datos['secuestro']);
                }

                $this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'id_tipoi', $datos['id_tipoi']);//-- le seteo el valor a una columna en una fila dada ---
                $this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'acta_completa', $d_actas[$i]['acta_completa']);
            }
            $this->get_relacion()->tabla('acta')->set_columna_valor('id_tramite',null); //pongo en null la columna del tramite
            $this->get_relacion()->tabla('acta')->set_columna_valor('id_tipoi',$datos['id_tipoi']); //--- me permite modificar todos los datos de la columna 'id_tipoi' por el nuevo TI
            if(count($d_actas) >= 1)
            {

            	toba::notificacion()->warning('Se va a modificar el Tipo de Infracci�n por el seleccionado actualmente y
            		se blanquearan los tr�mites anteriores');
            }
            $this->s__distintoi = 'true';
        }
		//-------------------------- Fin cambio Tipo de Infracción -----------------------------------------

		//----- Si se modifica el secuestro o dominio, se le modifica a todos los registros -----------------------
        if($d_actas[0]['secuestro'] != $datos['secuestro'] or $d_actas[0]['dominio'] != $datos['dominio'])
        {
        	foreach($d_actas as $i => $filas)
        	{
        		$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', $datos['secuestro']);
        		$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
        	}
        }
//------------------------------------------------------------------------------------------------------------------
//------------------ Verifico q el Acta no existe ------------------------------------------------------------------
//---------- Primero verifíco q no exista en la BD y luego en el DT -----------------------------------------------
        //$acta_con = "Select ac_nro From tribunal.actas Where ac_nro ='". $datos['ac_nro']."' and id_tipoi = '".$datos['id_tipoi']."' and ac_anio = '".$datos['ac_anio']."'";
        $banderaI="no";

        $acta_con = "Select acta_completa From tribunal.actas Where acta_completa = '".$datos['acta_completa']."'";
        $acta_query = toba::db()->consultar($acta_con);

        //Comparo con la longitud del acta vieja 6 digitos
        $acta_con_vie = "Select acta_completa From tribunal.actas Where acta_completa = '".$acta_vieja_completa."'";
        $acta_query_vie = toba::db()->consultar($acta_con_vie);
        if((count($acta_query) > 0) or (count($acta_query_vie) > 0))
        {
        	toba::notificacion()->error('El Nro. de Acta ya existe');
        }
        else //-------- el acta no se encuentra en la BD -------
        {
        	$cursores=null;
        	$acta = $this->get_relacion()->tabla('acta')->get_filas();
        	// if (in_array($id_nodo, $ids)) {
        
        	if(count($acta) > 0)
        	{ 
        		$cursores = $this->get_relacion()->tabla('acta')->get_id_fila_condicion(array('acta_completa' => $datos['acta_completa']));
        		 
        		// foreach($acta as $i => $fila)
        		// {
        		// 	if($acta[$i]['acta_completa'] == $datos['acta_completa'])
        		// 	{
          //               unset($datos['acta_completa']); //limpio el nro acta
          //               toba::notificacion()->error('El Nro. de Acta ya existe');
          //           }
          //       }
        		}
        		
        		
        		if (count($cursores[0])>0) {
        			unset($datos['acta_completa']); //limpio el nro acta
          			toba::notificacion()->error('El Nro. de Acta ya existe');
          			// echo "aqui";
        		}
        		else
        		{
        		if(isset($datos['acta_completa']))// si no esta vacia
                {
                	$cursor=$this->get_relacion()->tabla('acta')->nueva_fila($datos); $this->s__acta_nueva = true;
                	$this->get_relacion()->tabla('acta')->set_cursor($cursor);
                	$banderaI="si";
                }
            
            // else{

            // 	$cursor=$this->get_relacion()->tabla('acta')->nueva_fila($datos); $this->s__acta_nueva = true;
            // 	$this->get_relacion()->tabla('acta')->set_cursor($cursor);
            // 	$banderaI="si";
            // }	
        		}
                
        }
        if ($banderaI=="si"){
        	//cargo una image
        	$carpeta=$this->existe_carpeta();
        	
        	$auxiliar=$this->get_nombre_foto($datos['ac_nro']);
        	
        	if ($this->s__entorno_local) {
        		$sftp=$this->get_conexion_ssh2();
        		$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'], 'ssh2.sftp://'.$sftp.$carpeta.$auxiliar);
        	}else{   
        		$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'],$carpeta.$auxiliar);
			
        	}
        	if ($check){
        		if (!is_null($datos['nombre_imagen_up']) or (!is_null($datos['nombre_imagen']))) {
        			$datos['nombre_imagen']=$this->get_sub_carpeta().$auxiliar;
        			$this->get_relacion()->tabla('imagenes_actas')->set($datos); 
        			for ($i=0; $i < 999; $i++) { //cargo mi array de imagenes
        				if(is_null($this->s__img_tratar[$i])){
        					$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/subio';
        					break;
        				}                      
        			}
        		}
        	}else{
        		toba::notificacion()->warning('No se adjunto ninguna imagen al acta');
        	}
        // fin cargo imagen
        }
        $this->get_relacion()->tabla('acta')->resetear_cursor();
        $this->get_relacion()->tabla('imagenes_actas')->resetear_cursor();


    }
//------------------------------ Fin Verificación ------------------------------------------------


    function evt__frm_actas__modificacion($datos)
    {  
    	$datos['usu_mod'] = toba::usuario()->get_id();
    	$datos['fe_mod'] = date('d-m-Y');
        $datos['dominio'] =preg_replace('[\s+]',"", $datos['dominio']);//---- le quito los espacios de la cadena enviada

        $con = strlen($datos['ac_nro']);
        if($con < 8){
        	$datos['ac_nro']= str_pad($datos['ac_nro'],8,'0',STR_PAD_LEFT);
        	$acta_vieja=str_pad($datos['ac_nro'],6,'0',STR_PAD_LEFT);
        }

        if($datos['id_tipoi'] == '00')
        {
        	$datos['acta_completa'] = $datos['id_medio'].$datos['id_provincia'].$datos['id_municipio'].substr($datos['ac_anio'],2,2).$datos['ac_nro'];
        	$acta_vieja_completa = $datos['id_medio'].$datos['id_provincia'].$datos['id_municipio'].substr($datos['ac_anio'],2,2).$acta_vieja;
        	$datos['dominio'] = mb_convert_case($datos['dominio'], MB_CASE_UPPER, "LATIN1");
        }
        else{
        	$datos['acta_completa'] = $datos['ac_anio'].$datos['id_tipoi'].$datos['ac_nro'];
        	$acta_vieja_completa = $datos['ac_anio'].$datos['id_tipoi'].$datos['ac_nro'];
        	$datos['dominio'] = mb_convert_case($datos['dominio'], MB_CASE_UPPER, "LATIN1");
        	if($datos['secuestro'] == ''){
        		$datos['secuestro'] = null;
        	}
        }

        $this->s__infnew = $datos['id_tipoi'];

        $this->s__distintoi = false;


        #- Control: En caso de que se modifique el número de Acta, se verifica si el nuevo número existe en la Base de datos o en el Datos tabla, en cuyo caso no se permite la modificación para evitar duplicaciones.
        
        if($this->s__fila_acta['acta_completa'] != $datos['acta_completa'])
        {
        	$acta_con = "Select acta_completa 
        	From tribunal.actas 
        	Where acta_completa = '".$datos['acta_completa']."'";

        	$acta_query = toba::db()->consultar($acta_con);

        	$acta_con_vie = "Select acta_completa 
        	From tribunal.actas 
        	Where acta_completa = '".$datos['acta_completa']."'";

        	$acta_query_vie = toba::db()->consultar($acta_con_vie);

        	if($acta_query or $acta_query_vie){
        		toba::notificacion()->error('El Nro. de Acta ya existe');
        		$bandera = 'no modificar';
        		$this->get_relacion()->tabla('acta')->resetear_cursor();
        		$this->evento('procesar')->ocultar();
        	}
            else //-------- el acta no se encuentra en la BD -------
            {
            	$cursores=null;
            	$acta = $this->get_relacion()->tabla('acta')->get_filas();
            	if(count($acta) > 0)
            	{
            		$cursores = $this->get_relacion()->tabla('acta')->get_id_fila_condicion(array('acta_completa' => $datos['acta_completa']));
            		if (!empty($cursores)) {
        			unset($datos['acta_completa']); //limpio el nro acta
          			toba::notificacion()->error('El Nro. de Acta ya existe');
          			$bandera = 'no modificar';
          			$this->get_relacion()->tabla('acta')->resetear_cursor();
              		$this->evento('procesar')->ocultar();
        		}
            		// foreach($acta as $i => $fila)
            		// {
            		// 	if($acta[$i]['acta_completa'] == $datos['acta_completa'])
            		// 	{
              //               unset($datos['acta_completa']); //limpio el nro acta
              //               toba::notificacion()->error('El Nro. de Acta ya existe');
              //               $bandera = 'no modificar';
              //               $this->get_relacion()->tabla('acta')->resetear_cursor();
              //               $this->evento('procesar')->ocultar();
              //           }
              //       }

                    if(isset($datos['acta_completa']))// si no esta vacia
                    {
                    	$bandera = 'modificar';
                    }
                }else
                {
                	$bandera = 'modificar';
                }
            }
        }

        if($bandera != 'no modificar')
        {
        	$this->get_relacion()->tabla('acta')->set($datos);

        	if (is_null($this->s__fila_imagen)){
        		if (!is_null($datos['nombre_imagen_up'])){
                    #si quiero subir una imagen para un acta que no tenia imagen previamente
        			$this->modificar_foto($datos);
        			$datos['nombre_imagen']= $this->get_sub_carpeta().$this->get_nombre_foto($datos['ac_nro']);
        			$this->get_relacion()->tabla('imagenes_actas')->set($datos); 
        			for ($i=0; $i < 999; $i++) { //cargo el vector de imagenes
        				if(is_null($this->s__img_tratar[$i])){
        					$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod1';
        					break;
        				}                      
        			} 


        		}

        	}
        	else
        	{
                //si quiero modificar un acta con imagen
                #Si el numero de acta cargado no coincide con el de la base de datos
                #Hay que borrar de la bd la imagen con el nombre del acta anterior
                #Y guardar la imagen con el nuevo nombre del acta.
        		if ($this->s__fila_acta['ac_nro'] == $datos['ac_nro']){
        			if (!is_null($datos['nombre_imagen_up'])){
                            #si quiero subir un nuevo acta
        				$nombrefoto=$this->s__fila_imagen['nombre_imagen'];
        				$subcarpeta = explode('/',$this->s__fila_imagen['nombre_imagen']);
        				$carpeta_respaldo = $this->existe_carpeta_respaldo().$subcarpeta[1];                
        				$carpeta_vieja = $this->existe_carpeta_vieja($subcarpeta[0]).$subcarpeta[1];
        				if ($this->s__entorno_local) {
        					$sftp=$this->get_conexion_ssh2(); 
        					copy('ssh2.sftp://'.$sftp.$carpeta_vieja,'ssh2.sftp://'.$sftp.$carpeta_respaldo);
        				}
        				else{
        					copy($carpeta_vieja,$carpeta_respaldo);
        				}
        				$this->borrar_foto($this->s__fila_imagen['nombre_imagen']);
        				$this->modificar_foto($datos);
        				$datos['nombre_imagen']=$this->get_sub_carpeta().$this->get_nombre_foto($datos['ac_nro']);
        				$this->get_relacion()->tabla('imagenes_actas')->set($datos);
        				for ($i=0; $i < 999; $i++) { 
        					if(is_null($this->s__img_tratar[$i])){ //cargo el vector de imagenes
        						$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod2'.'/'.$this->s__fila_imagen['nombre_imagen'];
        						break;
        					}                      
        				}
        			}else{
                        //si no quiero modificar la imagen no hace nada de carga solo actualiza los otros datos
        				$this->get_relacion()->tabla('imagenes_actas')->set($datos);
        			}

        		}
        		else
                    {//si cambiamos el numero del acta
                    if (!is_null($datos['nombre_imagen_up'])){ //y queremos cargar una nueva imagen
                            #si quiero subir un nuevo acta
                    	$nombrefoto=$this->s__fila_imagen['nombre_imagen'];
                    	$subcarpeta = explode('/',$this->s__fila_imagen['nombre_imagen']);
                    	$carpeta_respaldo = $this->existe_carpeta_respaldo().$subcarpeta[1];                
                    	$carpeta_vieja = $this->existe_carpeta_vieja($subcarpeta[0]).$subcarpeta[1];
                    	if ($this->s__entorno_local) {
                    		$sftp=$this->get_conexion_ssh2(); 
                    		copy('ssh2.sftp://'.$sftp.$carpeta_vieja,'ssh2.sftp://'.$sftp.$carpeta_respaldo);


                    	}
                    	else{

                    		copy($carpeta_vieja,$carpeta_respaldo);

                    	}
                    	$this->borrar_foto($this->s__fila_imagen['nombre_imagen']);
                    	$this->modificar_foto($datos);
                    	$datos['nombre_imagen']=$this->get_sub_carpeta().$this->get_nombre_foto($datos['ac_nro']);
                    	$this->get_relacion()->tabla('imagenes_actas')->set($datos);
                    	for ($i=0; $i < 999; $i++) { 
                    		if(is_null($this->s__img_tratar[$i])){ //cargo el vector de imagenes
                    			$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod3'.'/'.$this->s__fila_imagen['nombre_imagen'];
                    			break;
                    		}                      
                    	}

                    }
                    else //queremos mantener la imagen
                    {
                    	$nombrefoto=$this->s__fila_imagen['nombre_imagen'];
                    	$nombrefoto=explode('/', $nombrefoto);
                    	$carpeta_vieja=$this->existe_carpeta_vieja($nombrefoto[0]);
                    	$imagen_vieja=$nombrefoto[1];
                    	$imagen_nueva=$this->get_nombre_foto($datos['ac_nro']);
                    	$datos['nombre_imagen']=$nombrefoto[0].'/'.$imagen_nueva;
                        //renombrar el archivo con el nuevo nombre
                    	$this->get_relacion()->tabla('imagenes_actas')->set($datos);
                            // ver como se renombra
                    	if ($this->s__entorno_local) {
                    		$sftp=$this->get_conexion_ssh2();
                    		ssh2_sftp_rename($sftp, $carpeta_vieja.$imagen_vieja, $carpeta_vieja.$imagen_nueva);

                    	} 
                    	else
                    	{
                    		rename($carpeta_vieja.$imagen_vieja, $carpeta_vieja.$imagen_nueva);
                    	}
                    	for ($i=0; $i < 999; $i++) { 
                    		if(is_null($this->s__img_tratar[$i])){ //cargo el vector de imagenes
                    			$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod4'.'/'.$this->s__fila_imagen['nombre_imagen'];
                    			break;
                    		}                      
                    	}

                    }

                }

            } 
            $this->get_relacion()->tabla('acta')->resetear_cursor();
            $this->get_relacion()->tabla('imagenes_actas')->resetear_cursor();
        }

//--------------------- Cambio de Tipo de Infracción y Blanqueo el Trámite -----------------------------
//------------------ Si se modifica el TI, se modifica todos los TI anteriores -------------------------
//------------------ Todos los Trámites de las Actas anteriores se blanquean ---------------------------

        $d_actas = $this->get_relacion()->tabla('acta')->get_filas();
        $d_actas =rs_ordenar_por_columna($d_actas, array('id_acta'), SORT_ASC); //--- ordeno mi array por el id ---

        if($this->s__infnew != $this->controlador()->get_inf_old())
        {
        	foreach($d_actas as $i => $filas)
        	{
        		/*************** Modifique 14/12 */
        		if(strlen($d_actas[$i]['ac_nro']) < 8)
        			$d_actas[$i]['ac_nro'] = str_pad($d_actas[$i]['ac_nro'],8,'0',STR_PAD_LEFT);

        		/********************************************** */
                if($datos['id_tipoi'] == '21')// Tipo de Inf: Foto Multa //
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
                    $this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', null); // blanquea el nro. de secuestro de todas las filas q estan en el DT //
                }
                elseif(($datos['id_tipoi'] >= '03') and ($datos['id_tipoi'] < '21'))
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', null);
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', null);
                }
                elseif(($datos['id_tipoi'] == '01') or ($datos['id_tipoi'] == '02'))
                {
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['ac_anio'].$datos['id_tipoi'].$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', $datos['secuestro']);
                }
                elseif($datos['id_tipoi'] == '00')
                {//----es 00 (SINAI) ----
                	$d_actas[$i]['acta_completa'] = $d_actas[$i]['id_medio'].$d_actas[$i]['id_provincia'].$d_actas[$i]['id_municipio'].substr($d_actas[$i]['ac_anio'],2,2).$d_actas[$i]['ac_nro'];
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', $datos['secuestro']);
                }

                if($this->s__fila_acta['ac_nro']  != $filas['ac_nro'])
                {
                	$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'id_tramite',null);
                }

                $cursor = $filas['x_dbr_clave'];
                $this->get_relacion()->tabla('acta')->set_cursor($cursor);
                $dts['acta_completa'] = $d_actas[$i]['acta_completa'];
                $this->get_relacion()->tabla('acta')->set($dts);
                $this->get_relacion()->tabla('acta')->resetear_cursor();
            }

            //$this->get_relacion()->tabla('acta')->set_columna_valor('id_tramite',null); //pongo en null la columna del tramite
            $this->get_relacion()->tabla('acta')->set_columna_valor('id_tipoi',$datos['id_tipoi']); //--- me permite modificar todos los datos de la columna 'id_tipoi' por el nuevo TI
            $this->s__distintoi = 'true';
            if(count($d_actas) >= 2)
            {
            	toba::notificacion()->warning('Se va a modificar el Tipo de Infracci�n por el seleccionado actualmente y se
            		blanquearan los tr�mites anteriores');
            }


        }

//-------------------------- Fin cambio Tipo de Infracción -----------------------------------------

//----- Si se modifica el secuestro o dominio, se le modifica a todos los registros -----------------------
        if($d_actas[0]['secuestro'] != $datos['secuestro'] or $d_actas[0]['dominio'] != $datos['dominio'])
        {
        	foreach($d_actas as $i => $filas)
        	{
        		$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'secuestro', $datos['secuestro']);
        		$this->get_relacion()->tabla('acta')->set_fila_columna_valor( $i,'dominio', $datos['dominio']);
        	}
        }
//------------------------------------------------------------------------------------------------------------------FIN MODIFICAR
    }

    function evt__frm_actas__cancelar()
    {
    	$this->get_relacion()->tabla('acta')->resetear_cursor();
    }

    function evt__frm_actas__baja()
    {
        $n_exp = $this->controlador()->get_nro_exp(); // acta q le dá número al expediente
        $acta_actual = $this->get_relacion()->tabla('acta')->get_columna('ac_nro'); // devuelve el nro de acta selecionada
        if($n_exp != $acta_actual)
        {
        	$this->get_relacion()->tabla('acta')->set();
        	$this->get_relacion()->tabla('imagenes_actas')->set();

        	if (!is_null($this->s__fila_imagen['nombre_imagen'])) {
        		for ($i=0; $i < 999; $i++) { //cargo el vector de imagenes
        			if(is_null($this->s__img_tratar[$i])){
        				$this->s__img_tratar[$i]['nombre'] = $this->s__fila_imagen['nombre_imagen'].'/bajo';

        				break;
        			}                   
        		}
        		$subcarpeta = explode('/',$this->s__fila_imagen['nombre_imagen']);
        		$carpeta_respaldo = $this->existe_carpeta_respaldo().$subcarpeta[1];                
        		$carpeta_vieja = $this->existe_carpeta_vieja($subcarpeta[0]).$subcarpeta[1];
        		if ($this->s__entorno_local) {
        			$sftp=$this->get_conexion_ssh2(); 
        			copy('ssh2.sftp://'.$sftp.$carpeta_vieja,'ssh2.sftp://'.$sftp.$carpeta_respaldo);


        		}
        		else{

        			copy($carpeta_vieja,$carpeta_respaldo); //hago un respaldo

        		}
        		$this->borrar_foto($this->s__fila_imagen['nombre_imagen']); //borro la foto
        	}
        }else{
        	toba::notificacion()->error('No puede eliminar este Acta, ya que di� origen al Expediente');
        }
    }


//---------------------------------------------------------------------------------------------------------------
//---------------------****** FORMULARIO DE MOTIVO DE RESORTEO *****---------------------------------------------
//---------------------------------------------------------------------------------------------------------------

    function conf__frm_motivo_resorteo(staf_ei_formulario $form)
    {
    	if($this->s__usuario == 'superusuario' and $this->get_relacion()->tabla('expediente')->esta_cargada())
    	{
    		if($this->s__distintop OR $this->s__distintoi)
    		{
    			$form->ef('motivo')->set_expandido(true);
    		}else{
    			$form->ef('motivo')->set_expandido(false);
    		}

    	}else{
    		$form->ef('motivo')->set_expandido(false);
    	}
//---- tuve q hacer este artificio p/ mantener el campo motivo siempre lleno(si es q lo cargaron) xq sino me pedia q lo cargue muchas veces ---
    	if(!is_null($this->s__motivo))
    	{
    		$form->ef('motivo')->set_estado($this->s__motivo);
    	}
    }


    function evt__frm_motivo_resorteo__alta($datos) // evento implícito
    {
    	if(!is_null($datos['motivo']))
    	{
    		$this->s__motivo = $datos['motivo'];
    	}
    }

//---------------------------------------------------------------------------------------------------------------
//-----------------------------****** EVENTOS DEL CI *****-------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------

    function evt__procesar()
    {
    	$this->s__continuar = true;
    	$this->s__resorteo= false;
        $medio = null; //-- esta variable se carga cuando el medio de constatacion es radar o pda --//

        if($this->s__nro_acta == 'existe')
        {
        	$exp = $this->get_relacion()->tabla('expediente')->get();
        	$acta = $this->get_relacion()->tabla('acta')->get_filas();


            //--- recorro las actas p determinar si alguna tiene Medio de constatacion = pda o radar ---//
        	foreach($acta as $m => $row){
        		if($row['id_medio'] == '06' or $row['id_medio'] == '09'){
                $medio = 'radar/pda'; //-- va al tribunal 5 --//
            }
        }

//-----------------------------------------------------------------------------
        if($this->s__tramite == 'no_existe'){
        	if($exp['id_prioridad'] == 2)
        	{
        		toba::notificacion()->error('La prioridad del expediente es Urgente, debe agregar un tramite al acta');
        		$this->s__continuar = false;
        	}
        }
//---------------------------------------------------------------------------
        if($this->s__continuar == true)
        {
        	if($this->s__acta_nueva == false)
                { //--- Si no hay un acta nueva puedo ordenar los registros x el id
                	$acta =rs_ordenar_por_columna($acta, array('id_acta'), SORT_ASC);

                	if($acta[0]['id_tipoi']== '00')
                	{
                		$nro_acta = substr($acta[0]['acta_completa'],9,8); /** Modifique 14/12 */
                	}else{
                		$nro_acta = substr($acta[0]['acta_completa'],8,8); /** Modifique 14/12 */
                	}

                }else{
                    $acta =rs_ordenar_por_columna($acta, array('id_acta'), SORT_ASC); // ---ordeno---
                    //--- busco el acta q tiene minimo id -------
                    $min = 999999;
                    foreach($acta as $i => $fila)
                    {
                    	if($acta[$i]['id_acta'] != null)
                    	{
                    		if($acta[$i]['id_acta'] < $min)
                    		{
                                $min = $acta[$i]['id_acta'];// guardo el minimo id ---
                                $acta[0]['id_acta'] = $min;
                                $acta[0]['acta_completa'] = $acta[$i]['acta_completa'];// guardo el acta del minimo id--
                            }
                        }
                    }
                    if($acta[0]['id_tipoi']== '00')  /**  Modifique 14/12 */
                    {
                    	$nro_acta = substr($acta[0]['acta_completa'],9,8);
                    }else{
                    	$nro_acta = substr($acta[0]['acta_completa'],8,8);
                    }

                }

                if($exp['n_expediente'] == '') //--- Se trata de un Alta de expediente
                {
                    //-- si el id_tipoi es 00 o 01 o 02 y id_medio es 06 o 09, se asigna el expediente al tribunal 5 (no se sortea) --//
                	/*if(($acta[0]['id_tipoi'] == '00' or $acta[0]['id_tipoi'] == '01' or $acta[0]['id_tipoi'] == '02') and ($acta[0]['id_medio'] == '06' or $acta[0]['id_medio'] == '09'))*/
                	if($acta[0]['id_tipoi'] == '00' and $medio == 'radar/pda')
                	{
                		$this->asignacion($exp,$acta,$nro_acta);
                		
                	}else{
                		$this->sorteo($exp,$acta,$nro_acta);
                		
                	}

                }
                elseif($exp['n_expediente'] != '') //----- es una modificacion
                {
                	if($this->s__usuario == 'superusuario')
                	{
                		if($this->s__distintop OR $this->s__distintoi OR ($this->controlador()->get_medio_old() != $medio))
                		{
                			$this->s__resorteo= true;
                            //----------------------------------------- Cargo la tabla de Resorteo -------------------------------------------------------------
                			$resorteo['motivo'] = $this->s__motivo;
                			$resorteo['id_expediente'] = $exp['id_expediente'];
                			$resorteo['id_tipoi_old'] = $this->controlador()->get_inf_old();
                			if($this->s__infnew == null)
                			{
                				$resorteo['id_tipoi_new'] = $this->controlador()->get_inf_old();
                			}else{
                				$resorteo['id_tipoi_new'] = $this->s__infnew;
                			}
                			$resorteo['id_prioridad_old'] = $this->controlador()->get_prioridad_old();
                			$resorteo['id_prioridad_new'] = $this->s__prinew;
                			$resorteo['n_expediente_old'] = $this->controlador()->get_expediente();
                			$resorteo['id_tribunal_old'] = $this->controlador()->get_tribunal();
                			$resorteo['fecha_hora'] = date('d-m-Y H:i:s');
                			$resorteo['usuario'] = toba::usuario()->get_id();
                			$cursor = $this->get_relacion()->tabla('resorteo')->nueva_fila($resorteo);
                			$this->get_relacion()->tabla('resorteo')->set_cursor($cursor);

                			if($acta[0]['id_tipoi'] == '00' and $medio == 'radar/pda')
                			{
                				$sorteo = $this->asignacion($exp,$acta,$nro_acta);
                			}else{
                				$sorteo = $this->sorteo($exp,$acta,$nro_acta);
                			}
                			$resorteo1['n_expediente_new'] = $sorteo['n_expediente'];
                			$resorteo1['id_tribunal_new'] = $sorteo['id_tribunal'];

                			$exp['id_tribunal'] = $resorteo1['id_tribunal_new'];
                			$exp['n_expediente'] = $resorteo1['n_expediente_new'];
                			$this->get_relacion()->tabla('resorteo')->set($resorteo1);
                			$this->get_relacion()->tabla('expediente')->set($exp);
//------------------------------- Fin de la carga ----------------------------------------------------
                			if($resorteo['id_prioridad_old']  == 2)
                			{
                                //se disminuye el contador Urgente del juzgado seleccionado dependiendo del tipo de infraccion
                				$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_urgentes = (c_urgentes - 1) WHERE id_juzgado = '".$resorteo['id_tribunal_old']."' and id_tipoi= '".$resorteo['id_tipoi_old']."'";
                				toba::db()->consultar($sql1);
                			}elseif($resorteo['id_prioridad_old']  == 1)
                			{
                                //se diminuye el contador Comunes del juzgado seleccionado dependiendo del tipo de infraccion
                				$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_comunes = (c_comunes - 1) WHERE id_juzgado = '".$resorteo['id_tribunal_old']."' and id_tipoi= '".$resorteo['id_tipoi_old']."'";
                				toba::db()->consultar($sql1);
                			}
                		}else{
                			$exp['n_expediente'] = $acta[0]['ac_anio'].$acta[0]['id_tipoi'].$acta[0]['id_medio'].$nro_acta;
                			
                			$this->get_relacion()->tabla('expediente')->set($exp);
                		}
                	}
                	// elseif($this->s__usuario == 'supervisor')
                	// {
                	// 	$exp['n_expediente'] = $acta[0]['ac_anio'].$acta[0]['id_tipoi'].$acta[0]['id_medio'].$nro_acta;
                	// 	$this->get_relacion()->tabla('expediente')->set($exp);
                	// }
                }

//-------------------------------- Controlo que si es Urgente,tenga un trámite y si es Comun,no lo tenga ----------------
                try{
                if($this->s__tramite == 'existe')
                {

                	if($exp['id_prioridad'] == 1)
                	{
                		toba::notificacion()->error('Error al guardar en la Base de Datos');
                	}else{
                        //$this->get_relacion()->sincronizar();
                		$this->sincronizar();
                		$this->s__img_tratar = array(); //Si se sincroniza (se vacia el vector de imagenes, ya que no sera necesario hacer las restauraciones)
                		$this->controlador()->set_pantalla('pant_inicial_exp');
                	}

                }elseif($this->s__tramite == 'no_existe'){
                	if($exp['id_prioridad'] == 1)
                	{
                        //$this->get_relacion()->sincronizar();
                		$this->sincronizar();
                		$this->s__img_tratar = array(); //Si se sincroniza (se vacia el vector de imagenes, ya que no sera necesario hacer las restauraciones)
                		$this->controlador()->set_pantalla('pant_inicial_exp');
                	}
                }
                }
    		catch(toba_error $e){
    			
    			$sql = "SELECT descripcion FROM public.errores_sql WHERE id_sqlstate = '".$e->get_sqlstate()."'";
    			$rs = toba::db()->consultar($sql);
    			
    			if(count($rs) > 0){
    				$mensaje = $rs[0]['descripcion'];
    			}
    			else{
    				$mensaje = $e->get_mensaje_motor();
    			}
    			toba::notificacion()->agregar($mensaje);
    		}
//-------------------------------------------------- Fin del Control --------------------------------------------------------
            }
        }else{
        	toba::notificacion()->error('Debe agregar un acta');
        	$this->set_pantalla('pant_actas');
        }
    }

    function evt__cancelar()
    {
    	$this->s__img_tratar=array_reverse($this->s__img_tratar); //invierto el orden del vector para ir deshaciendo las acciones realizadas con los archivos en el servidor
    	foreach($this->s__img_tratar as $i => $fila){

    		if($fila){
    			$nombre = explode('/',$fila['nombre']);
    			if($nombre[2] == 'subio'){ //caso del alta
    				$this->borrar_foto($fila['nombre']);
    			}
    			if($nombre[2] == 'bajo'){ //caso del baja
    				$this->agregar_foto($fila['nombre']);

    			}
    			if($nombre[2] == 'mod1'){ // caso de modificacion donde  se creo una registro en el servidor
    				$this->borrar_foto($fila['nombre']);

    			}
    			if($nombre[2] == 'mod2'){ // caso de modificacion donde se piso un archivo en el servidor
    				$aux1=$nombre[3].'/'.$nombre[4];
    				$aux2=$nombre[0].'/'.$nombre[1];
    				$this->borrar_foto($aux2);
                    $this->agregar_foto($aux1);

                  }
                  if($nombre[2] == 'mod3'){ //caso de modificacion donde se borro y creo un archivo en el servidor
                  	$aux1=$nombre[3].'/'.$nombre[4];
                  	$aux2=$nombre[0].'/'.$nombre[1];
                  	$this->borrar_foto($aux2);
                    $this->agregar_foto($aux1);
                  }
                  if($nombre[2] == 'mod4'){ //caso de modificacion donde se renombro un archivo en el servidor
                   $this->renombrar_foto($fila['nombre']);//hacer el metodo
               }
           }
       }
       // se limpian la memoria y variables
       toba::memoria()->eliminar_dato('tipo_inf');
       toba::memoria()->eliminar_dato('prioridad');
       $this->s__nro_exp = null;
       $this->s__nro_acta = null;
       $this->s__tribunal = null;
       $this->s__expediente = null;
       $this->s__tramite = null;
       $this->s__prinew = null;
       $this->s__priold = null;
       $this->s__usuario = false;
       $this->s__infnew = null;
       $this->s__infold = null;
       $this->s__distintop = false;
       $this->s__distintoi = false;
       $this->s__resorteo = false;
       $this->s__motivo = null;
       $this->s__continuar = true;
       $this->s__acta_nueva = false;
       $this->s__fila_acta = null;
       $this->s__fila_imagen = null;
       $this->s__img_tratar = array();
       $this->get_relacion()->resetear();
       $this->controlador()->set_pantalla('pant_inicial_exp');
   }

   function sincronizar()
   {
   	try
   	{
   		toba::memoria()->eliminar_dato('tipo_inf');
   		toba::memoria()->eliminar_dato('prioridad');
   		$this->s__nro_acta = null;
   		$this->s__nro_exp = null;
   		$this->s__tribunal = null;
   		$this->s__expediente = null;
   		$this->s__tramite = null;
   		$this->s__prinew = null;
   		$this->s__priold = null;
   		$this->s__usuario = false;
   		$this->s__infnew = null;
   		$this->s__infold = null;
   		$this->s__distintop = false;
   		$this->s__distintoi = false;
   		$this->s__resorteo = false;
   		$this->s__motivo = null;
   		$this->s__continuar = true;
   		$this->s__acta_nueva = false;
   		$this->s__fila_acta = null;
   		$this->s__fila_imagen=null;
   		$this->get_relacion()->sincronizar();
   		$this->get_relacion()->resetear();

   	}
   	catch(Exception $e)
   	{
   		toba::notificacion()->error('Error al sincronizar con la Base de Datos',$e);
   	}
   }

   /**  ----------------------------- **** SORTEO **** --------------------------------- **/
   function sorteo($exp,$acta,$nro_acta)
   {
   	if($exp['id_prioridad'] == 2)
   	{
   		$con = "Select count(id_juzgado) From tribunal.trib_tipoinfraccion Where id_tipoi='".$acta[0]['id_tipoi']."' and activo = '1'";
   		$rdo1 = toba::db()->consultar($con);
            if($rdo1[0]['count'] >= 3) //--- Son mas o 3 juzgados los q atienden ese tipo de infraccion
            {
                //la consulta me devuelve el id y desc del juzgado, id y desc del tipo de infraccion y el contador, ordenados por el contador
            	$sql1 = "Select tr.id_tribunal, tr.descripcion as desc_trib, jti.c_urgentes, ti.id_tipoi, ti.descripcion as desc_tipo
            	From tribunal.tribunales as tr, tribunal.tipo_infraccion as ti, tribunal.trib_tipoinfraccion as jti
            	Where tr.id_tribunal = jti.id_juzgado and ti.id_tipoi = jti.id_tipoi and
            	ti.id_tipoi = '".$acta[0]['id_tipoi']."' and jti.activo = '1'
            	order by c_urgentes";
            	$rs1 = toba::db()->consultar($sql1);

                //cargo en un array todos los juzgados q atienden ese tipo de infraccion
            	$i=0;
            	foreach($rs1 as $k =>$reg)
            	{
            		$rs1[$k]=$reg['id_tribunal'];
            		$i++;
            	}

                // realiza un random entre los 3 primeros  (CAMBIO A 2 PRIMEROS)
            	mt_srand (time());
            	$n = mt_rand(0,1);
            	$id=$rs1[$n];

                //se aumenta el contador Urgente del juzgado seleccionado dependiendo del tipo de infraccion
            	$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_urgentes = (c_urgentes + 1) WHERE id_juzgado = '".$id."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql1);
            }elseif($rdo1[0]['count'] == 2)
            {
            	$sql_1 = "Select t.id_tribunal, t.descripcion as desc_trib, jti.c_urgentes, ti.id_tipoi, ti.descripcion as desc_ti
            	From tribunal.tribunales as t, tribunal.tipo_infraccion as ti, tribunal.trib_tipoinfraccion as jti
            	Where t.id_tribunal = jti.id_juzgado and ti.id_tipoi = jti.id_tipoi and
            	ti.id_tipoi = '".$acta[0]['id_tipoi']."' and jti.activo = '1'
            	order by c_urgentes";
            	$rs = toba::db()->consultar($sql_1);

                //cargo en un array todos los juzgados q atienden ese tipo de infraccion
            	$i=0;
            	foreach($rs as $k=>$reg)
            	{
            		$rs[$k]=$reg['id_tribunal'];
            		$i++;
            	}

                // realiza un random entre los 2 primeros
            	mt_srand (time());
            	$n = mt_rand(0,1);
            	$id=$rs[$n];

                //se aumenta el contador del tribunal seleccionado
            	$sql2 = "UPDATE tribunal.trib_tipoinfraccion SET c_urgentes = (c_urgentes + 1) WHERE id_juzgado = '".$id."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql2);
            }elseif($rdo1[0]['count'] == 1)
            {
            	$id_juz ="Select id_juzgado From tribunal.trib_tipoinfraccion Where id_tipoi='".$acta[0]['id_tipoi']."' and activo = '1'";
            	$id = toba::db()->consultar($id_juz);

                //se aumenta el contador del tribunal seleccionado
            	$sql3 = "UPDATE tribunal.trib_tipoinfraccion SET c_urgentes = (c_urgentes + 1) WHERE id_juzgado = '".$id[0]['id_juzgado']."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql3);
            }
        }elseif($exp['id_prioridad'] == 1)
        {
        	$con = "Select count(id_juzgado) From tribunal.trib_tipoinfraccion Where id_tipoi='".$acta[0]['id_tipoi']."' and activo = '1'";
        	$rdo1 = toba::db()->consultar($con);
            if($rdo1[0]['count'] >= 3) //--- Son mas o 3 juzgados los q atienden ese tipo de infraccion
            {
                //la consulta me devuelve el id y desc del juzgado, id y desc del tipo de infraccion y el contador, ordenados por el contador
            	$sql1 = "Select tr.id_tribunal, tr.descripcion as desc_trib, jti.c_comunes, ti.id_tipoi, ti.descripcion as desc_tipo
            	From tribunal.tribunales as tr, tribunal.tipo_infraccion as ti, tribunal.trib_tipoinfraccion as jti
            	Where tr.id_tribunal = jti.id_juzgado and ti.id_tipoi = jti.id_tipoi and
            	ti.id_tipoi = '".$acta[0]['id_tipoi']."' and jti.activo = '1'
            	order by c_comunes";
            	$rs1 = toba::db()->consultar($sql1);

                //cargo en un array todos los juzgados q atienden ese tipo de infraccion
            	$i=0;
            	foreach($rs1 as $k =>$reg)
            	{
            		$rs1[$k]=$reg['id_tribunal'];
            		$i++;
            	}

                // realiza un random entre los 3 primeros (CAMBIO A 2 PRIMEROS)
            	mt_srand (time());
            	$n = mt_rand(0,1);
            	$id=$rs1[$n];

                //se aumenta el contador Comun del juzgado seleccionado dependiendo del tipo de infraccion
            	$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_comunes = (c_comunes + 1) WHERE id_juzgado = '".$id."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql1);
            }elseif($rdo1[0]['count'] == 2)
            {
            	$sql_1 = "Select t.id_tribunal, t.descripcion as desc_trib, jti.c_comunes, ti.id_tipoi, ti.descripcion as desc_ti
            	From tribunal.tribunales as t, tribunal.tipo_infraccion as ti, tribunal.trib_tipoinfraccion as jti
            	Where t.id_tribunal = jti.id_juzgado and ti.id_tipoi = jti.id_tipoi and
            	ti.id_tipoi = '".$acta[0]['id_tipoi']."' and jti.activo = '1'
            	order by c_comunes";
            	$rs = toba::db()->consultar($sql_1);

                //cargo en un array todos los juzgados q atienden ese tipo de infraccion
            	$i=0;
            	foreach($rs as $k=>$reg)
            	{
            		$rs[$k]=$reg['id_tribunal'];
            		$i++;
            	}

                // realiza un random entre los 2 primeros
            	mt_srand (time());
            	$n = mt_rand(0,1);
            	$id=$rs[$n];

                //se aumenta el contador del tribunal seleccionado
            	$sql2 = "UPDATE tribunal.trib_tipoinfraccion SET c_comunes = (c_comunes + 1) WHERE id_juzgado = '".$id."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql2);
            }elseif($rdo1[0]['count'] == 1)
            {
            	$id_juz ="Select id_juzgado From tribunal.trib_tipoinfraccion Where id_tipoi='".$acta[0]['id_tipoi']."' and activo = '1'";
            	$id = toba::db()->consultar($id_juz);

                //se aumenta el contador del tribunal seleccionado
            	$sql3 = "UPDATE tribunal.trib_tipoinfraccion SET c_comunes = (c_comunes + 1) WHERE id_juzgado = '".$id[0]['id_juzgado']."' and id_tipoi= '".$acta[0]['id_tipoi']."'";
            	toba::db()->consultar($sql3);
            }
        }
        $exp['n_expediente'] = $acta[0]['ac_anio'].$acta[0]['id_tipoi'].$acta[0]['id_medio'].$nro_acta;
        if($rdo1[0]['count'] == 1)
        {
        	$exp['id_tribunal'] = $id[0]['id_juzgado'];
        }else{
        	$exp['id_tribunal'] = $id;
        }
        $this->get_relacion()->tabla('expediente')->set($exp);

        //----Impacto la Carga del Expediente en la tabla Movimientos con el tribunal al cual fue asignado------
        if($this->s__resorteo == false)
        {
        	$exp['id_motivo']=1;
        	$exp['id_mov_destino']=1;
        	$exp['fecha']= $exp['fe_alta'];
        	$exp['usuario']= toba::usuario()->get_id();
        	$this->get_relacion()->tabla('movimiento')->nueva_fila($exp);
        }elseif($this->s__resorteo == true)
        {
        	$exp['id_motivo']=2;
        	$exp['id_mov_destino']=1;
        	$exp['fecha']= date('d-m-Y');
        	$exp['usuario']= toba::usuario()->get_id();
        	$this->get_relacion()->tabla('movimiento')->nueva_fila($exp);
        }

        return ($exp);

    }

    /** -------------------------- **** ASIGNACION **** ----------------------- **/
    //-- Cuando el id_medio es radar o pda no se sortea, se asigna directamente al juzgado 5 --//
    function asignacion($exp,$acta,$nro_acta)
    {
    	if($exp['id_prioridad'] == 2)
    	{
            // se aumenta el contador Urgente del juzgado 6 //
    		$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_urgentes = (c_urgentes + 1) WHERE id_juzgado = '5' and id_tipoi= '".$acta[0]['id_tipoi']."'";
    		toba::db()->consultar($sql1);
    	}elseif($exp['id_prioridad'] == 1)
    	{
            // se aumenta el contador Comun del juzgado 6 //
    		$sql1 = "UPDATE tribunal.trib_tipoinfraccion SET c_comunes = (c_comunes + 1) WHERE id_juzgado = '5' and id_tipoi= '".$acta[0]['id_tipoi']."'";
    		toba::db()->consultar($sql1);
    	}

    	$exp['n_expediente'] = $acta[0]['ac_anio'].$acta[0]['id_tipoi'].$acta[0]['id_medio'].$nro_acta;
    	$exp['id_tribunal'] = '5';
    	$this->get_relacion()->tabla('expediente')->set($exp);

        //---- Impacto la Carga del Expediente en la tabla Movimientos con el tribunal al cual fue asignado ------
    	if($this->s__resorteo == false)
    	{
    		$exp['id_motivo']=1;
    		$exp['id_mov_destino']=1;
    		$exp['fecha']= $exp['fe_alta'];
    		$exp['usuario']= toba::usuario()->get_id();
    		$this->get_relacion()->tabla('movimiento')->nueva_fila($exp);
    	}elseif($this->s__resorteo == true)
    	{
    		$exp['id_motivo']=2;
    		$exp['id_mov_destino']=1;
    		$exp['fecha']= date('d-m-Y');
    		$exp['usuario']= toba::usuario()->get_id();
    		$this->get_relacion()->tabla('movimiento')->nueva_fila($exp);
    	}

    	return ($exp);
    }

//------------------------------- Se utiliza para armar la seleccion en cascada---------------------------------
    function get_tipo_inf($tipo_inf)
    {
    	if($this->get_relacion()->tabla('expediente')->get_columna('id_prioridad') == 2)
    	{
    		$sql ="Select ti.id_tipoi, tr.id_tramite, ti.descripcion as tipo_inf, tr.descripcion as tram From tribunal.tipo_infraccion ti
    		Inner join tribunal.tram_tipoinf tt on ti.id_tipoi = tt.id_tipoi
    		Inner join tribunal.tramites tr on tr.id_tramite = tt.id_tramite Where ti.id_tipoi = '".$tipo_inf."'";
    		return toba::db()->consultar($sql);
    	}
    }

    function get_provincia($prov)
    {
    	$sql ="Select p.c06id, l.c07id, p.c06pcia, l.c07localidad
    	From public.scm006 p
    	Inner Join public.scm007 l On p.c06id=l.c07id_pcia
    	Where c06id='".$prov."'";
    	return toba::db()->consultar($sql);
    }
//------------------------------------------------------------------------------------------------------------------

    function get_combo_editableId($id = null)
    {
    	return $id;
    }

#------ función con ajax para buscar los datos del archivo
    function ajax__get_dtos_archivo($id, toba_ajax_respuesta $respuesta)
    {    
    	$sql = "SELECT * 
    	From tribunal.par_archivos 
    	where id_archivo=$id";
    	$rs = toba::db()->consultar($sql);

    	if(count($rs) > 0) 
    	{
    		$rs = $rs[0];
    	}
    	$respuesta->set($rs);
    }  
     #------ función con ajax para buscar los datos de la persona
    function ajax__get_dtos_persona($id, toba_ajax_respuesta $respuesta)
    {    
    	$sql = "with per as ( --datos de la persona que aparecen solo una vez
    	SELECT pp.id_persona, COALESCE(pn.apyn, pj.razon_social) AS nombre, email      
    	FROM cidig.persona                     pp
    	LEFT JOIN cidig.persona_natural   pn ON pn.id_persona = pp.id_persona
    	LEFT JOIN cidig.persona_juridica  pj ON pj.id_persona = pp.id_persona
    	WHERE pp.id_persona = $id),

    	cel as (
    	select * from (
    	SELECT pp.id_persona, case when tp.id_telefono_tipo = 3 then numero when tp.id_telefono_tipo = 4 then numero end as celular 
    	FROM cidig.persona                     pp
    	LEFT JOIN cidig.telefono_persona  tp ON tp.id_persona = pp.id_persona and tp.id_estado = 1
    	LEFT JOIN cidig.telefono_tipo     tt ON tt.id_telefono_tipo = tp.id_telefono_tipo
    	WHERE pp.id_persona = $id and numero is not null ) xc
    	where celular is not null
    	limit 1),

    	tel as (
    	select * from (
    	SELECT pp.id_persona, case when tp.id_telefono_tipo = 1 then numero when tp.id_telefono_tipo = 2 then numero end as telefono 
    	FROM cidig.persona                     pp
    	LEFT JOIN cidig.telefono_persona  tp ON tp.id_persona = pp.id_persona and tp.id_estado = 1
    	LEFT JOIN cidig.telefono_tipo     tt ON tt.id_telefono_tipo = tp.id_telefono_tipo
    	WHERE pp.id_persona = $id and numero is not null ) xt
    	where telefono is not null
    	limit 1),

    	dom as (
    	SELECT pp.id_persona, case when (d.id_barrio is not null and d.id_calle is not null and altura is not null) 
    	then b.nombre_barrio || ', ' || c.nombre || ' ' || altura
    	when (d.id_barrio is null and d.id_calle is not null and altura is not null) 
    	then c.nombre || ' ' || altura
    	else d.otro_dato_domi end as domicilio      
    	FROM cidig.persona                     pp
    	LEFT JOIN cidig.persona_domicilio pd ON pd.id_persona = pp.id_persona
    	LEFT JOIN cidig.domicilio          d on d.id_domicilio = pd.id_domicilio
    	LEFT JOIN public.barrios_185       b on b.id_barrio = d.id_barrio
    	LEFT JOIN public.calles_185        c on c.id_calle = d.id_calle 
    	WHERE pp.id_persona = $id
    	limit 1)

    	select p.nombre, c.celular as ini_celular, t.telefono as ini_te_fijo, p.email as ini_email, d.domicilio
    	from per p
    	left join cel c on c.id_persona = p.id_persona
    	left join tel t on t.id_persona = p.id_persona
    	left join dom d on d.id_persona = p.id_persona";
    	$rs = toba::db()->consultar($sql);

    	if(count($rs) > 0) 
    	{
    		$rs = $rs[0];
    	}
    	$respuesta->set($rs); 
    }  



    //-----------------------------------------------------------------------------------
    //---- cd_estados -------------------------------------------------------------------
    //-----------------------------------------------------------------------------------

    function conf__cd_estados(staf_ei_cuadro $cuadro)
    {
        /*$query = "SELECT ta.id_accion, tta.nombre, ta.fecha, ta.observaciones
        FROM tribunal.acciones ta 
        INNER JOIN tribunal.tipo_accion tta ON tta.id_tipo_accion = ta.id_tipo_accion
        WHERE ta.id_expediente = $this->s__idExpe
        ORDER BY ta.id_accion DESC";*/

        $dt_cd = $this->get_relacion()->tabla('acciones')->get_filas();
        $dt_acta = $this->get_relacion()->tabla('acta')->get_filas();

        if(count($dt_cd) > 0 && count($dt_acta) > 0)
        {
        	$this->s__nro_acta = 'existe';
        	if(isset($dt_acta[0]['id_tramite'])){

        		$this->s__tramite = 'existe';
        	}else{

        		$this->s__tramite = 'no_existe';
        	}


        	foreach($dt_cd as $i => $filas){

        		$sql_estado = "SELECT tta.nombre FROM tribunal.acciones ta
        		LEFT JOIN tribunal.tipo_accion tta ON tta.id_tipo_accion = {$filas['id_tipo_accion']}";

        		$rs = toba::db()->consultar($sql_estado);
        		if(count($rs) > 0)
        		{
        			$dt_cd[$i]['nombre'] = $rs[0]['nombre'];
                   // $dt_cd[$i]['fecha']=date("d-m-Y",strtotime($dt_cd[$i]['fecha']));
        		}
        	}
        }
		#- Agrego al cuadro columna (no visible) con el nombre de la imagen
		$hay_cursor = false;
		if($this->get_relacion()->tabla('acciones')->hay_cursor()){
			$old_cursor = $this->get_relacion()->tabla('acciones')->get_cursor();
			$hay_cursor = true;
		}
		foreach ($dt_cd as $key => $fila) {
			$this->get_relacion()->tabla('acciones')->set_cursor($fila['x_dbr_clave']);
			$imagenes = $this->get_relacion()->tabla('imagenes_acciones')->get_filas();
			if(count($imagenes)>0){
				$dt_cd[$key]['nombre_imagen'] = $imagenes[0]['nombre_imagen'];
			}else{
				$dt_cd[$key]['nombre_imagen'] = null;
			}
		}
		if($hay_cursor){
			$this->get_relacion()->tabla('acciones')->set_cursor($old_cursor);
		}else{
			$this->get_relacion()->tabla('acciones')->resetear_cursor();
		}

        $cuadro->set_datos($dt_cd);

        $cuadro->set_titulo($cuadro->get_titulo(). ' ' .$this->s__numExpe);

    }

    //-----------------------------------------------------------------------------------
    //---- frm_nuevo_estado -------------------------------------------------------------
    //-----------------------------------------------------------------------------------

    function conf__frm_nuevo_estado(staf_ei_formulario $form)
    {
    	$fecha_actual = date('d-m-Y');
    	$id_dependencia = "SELECT id_dep, c01leyen FROM tribunal.dependencias_habilitadas where c01depresu::BIGINT =".$this->s__tribunal_dependencia;
		$rs = toba::db()->consultar($id_dependencia);
		
    	
    	if($this->get_relacion()->tabla('acciones')->hay_cursor())
    	{
    		$datos = $this->get_relacion()->tabla('acciones')->get();
             //devuelve la fecha completa
            /*$fecha_a = $datos['fecha'];
            $form->ef('fecha')->set_estado($fecha_a); //muestro la fecha actual en el campo fecha de alta
            $form->ef('fecha')->set_solo_lectura();
            $form->ef('observaciones')->set_estado($datos['observaciones']);*/
			if($this->get_relacion()->tabla('imagenes_acciones')->hay_cursor()){
				$reg = $this->get_relacion()->tabla('imagenes_acciones')->get();
				$datos['nombre_imagen'] = $reg['nombre_imagen'];
				$datos['descripcion'] = $reg['descripcion'];
			}
            $form->set_datos($datos);
			$conexion=$this->conexion();
			$nombreimagen=$this->get_relacion()->tabla('imagenes_acciones')->get_columna('nombre_imagen');
			$nombreimagen=explode('/',$nombreimagen);
			if ($this->s__entorno_local) {
				$conexion="http://desarrollo.ciudaddecorrientes.gov.ar";
				$form->ef('ver_imagen')->set_estado("<embed src='".$conexion.$this->s__carpeta_imagenes.$nombreimagen[0].'/'.
                $nombreimagen[1]."' type='application/pdf' height='300' width='500'>");
			}else{
                $form->ef('ver_imagen')->set_estado("<embed src='".$this->s__carpeta_imagenes.$nombreimagen[0].'/'.
					$nombreimagen[1].
					"' type='application/pdf' height='300' width='500'>");
			}
       
        }
        else{
        //$datos['desc_dependencia']=$rs[0]['c01leyen'];
		//$datos['id_dependencia']=$rs[0]['id_dep'];

        	       	
        	$form->ef('desc_dependencia')->set_estado($rs[0]['c01leyen']);
        	 
            //$form->ef('fecha')->set_estado($fecha_actual); //muestro la fecha actual en el campo fecha de alta
            //$form->ef('fecha')->set_solo_lectura();

            $form->set_datos($datos);
            $datos['desc_dependencia']=$rs[0]['c01leyen'];
			$datos['id_dependencia']=$rs[0]['id_dep'];

        }
        
        
    }

    function evt__frm_nuevo_estado__agregar($datos)
    {
    	// $bandera =true;
    	// $estados = $this->get_relacion()->tabla('acciones')->get_filas();
    	// foreach($estados as $i => $filas){
    	// 	if ($estados[$i]['id_tipo_accion'] == $datos['id_tipo_accion']){
    	// 		$bandera = false;
    	// 	}
    	// }

    	// if($bandera) {
    		$datos['id_expediente'] = $this->s__idExpe;
    		$datos['usuarioalta'] = $this->s__usuAlta;
    		$datos['usuariomod'] = $this->s__usuMod;
    		$datos['fechacarga'] = date('d-m-Y');
    		$datos['fechamod'] = null;

    		
			
			//cargo una image
			$carpeta=$this->existe_carpeta();
			$auxiliar=$this->get_nombre_foto_acciones($carpeta);
			$cursor =$this->get_relacion()->tabla('acciones')->nueva_fila($datos);
	
			if ($this->s__entorno_local) {
			   
				$sftp=$this->get_conexion_ssh2();
				$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'], 'ssh2.sftp://'.$sftp.$carpeta.$auxiliar);
				
			}else{   
				
				$check=move_uploaded_file($datos['nombre_imagen_up']['tmp_name'],$carpeta.$auxiliar);
			}
			if ($check){
			   
				if (!is_null($datos['nombre_imagen_up'])) {
				  
					$datos['nombre_imagen']=$this->get_sub_carpeta().$auxiliar;
					$this->get_relacion()->tabla('acciones')->set_cursor($cursor);
					$this->get_relacion()->tabla('imagenes_acciones')->nueva_fila($datos);
					for ($i=0; $i < 999; $i++) { //cargo mi array de imagenes
						if(is_null($this->s__img_tratar[$i])){
							$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/subio';
							break;
						}                      
					}
				}
			}else{
				
				toba::notificacion()->warning('No se adjunto ninguna imagen a la accion');
			}
		
		$this->get_relacion()->tabla('acciones')->resetear_cursor();
		$this->get_relacion()->tabla('imagenes_acciones')->resetear_cursor();
    	// }else {
    	// 	toba::notificacion()->error('El estado ya fue registrado.');

    	// }

    }

    function sincronizar_acciones(){

    	try
    	{
    		$this->get_relacion()->tabla('acciones')->sincronizar();
    		$this->get_relacion()->tabla('acciones')->resetear();

    	}
    	catch(Exception $e)
    	{
    		toba::notificacion()->error('Error al sincronizar con la Base de Datos',$e);
    	}

    }

    function evt__cd_estados__seleccion($seleccion)
    {
    	$this->get_relacion()->tabla('acciones')->set_cursor($seleccion);
    	$actuacion = $this->get_relacion()->tabla('acciones')->get($seleccion);
    	if (is_null($actuacion['fechacarga']))
    	{
    		$this->s__fechacarga = $actuacion['fecha'];
    	}else{
    		$this->s__fechacarga = $actuacion['fechacarga'];
    	}
		#- Ubico el cursor en la tabla de imágenes. Esto se hace de esta manera dado que, por el momento, sólo puede existir un registro de imágenes por Acta
        if($this->get_relacion()->tabla('imagenes_acciones')->get_cantidad_filas()){
			$cursores = $this->get_relacion()->tabla('imagenes_acciones')->get_filas()[0]['x_dbr_clave'];
			$this->get_relacion()->tabla('imagenes_acciones')->set_cursor($cursores);
			$this->s__fila_acciones = $this->get_relacion()->tabla('imagenes_acciones')->get();
        }
    }

    function evt__frm_nuevo_estado__modificacion($datos)
    {
    	$datos['fechacarga'] =  $this->s__fechacarga;

    	if($datos['fecha'] >= $datos['fechacarga']){
    		$datos['fechamod'] = date('d-m-Y');
    		$this->get_relacion()->tabla('acciones')->set($datos);
    	}else {
    		toba::notificacion()->error('la fecha debe ser mayor a la fecha que fue cargada la actuacion');

    	}

		if (!is_null($datos['nombre_imagen_up'])){
			if  (is_null($this->s__fila_acciones['nombre_imagen'])){
           		#si quiero subir una imagen para una accion que no tenia imagen previamente
            	$nombre=$this->modificar_foto_acciones($datos);
            	$datos['nombre_imagen']= $nombre;
            	$this->get_relacion()->tabla('imagenes_acciones')->set($datos); 
            		for ($i=0; $i < 999; $i++) { //cargo el vector de imagenes
                		if(is_null($this->s__img_tratar[$i])){
                    		$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod1';
                    		break;
                		}                      
            		}  
            }else{
				#si quiero cambiar una imagen para una accion que ya tenia imagen previamente
            	$this->salvar_foto_acciones($this->s__fila_acciones['nombre_imagen']);
            	$nombre=$this->modificar_foto_acciones($datos);
            	$datos['nombre_imagen']= $nombre;
            	$this->get_relacion()->tabla('imagenes_acciones')->set($datos); 
            	for ($i=0; $i < 999; $i++) { 
                	if(is_null($this->s__img_tratar[$i])){ //cargo el vector de imagenes
                    	$this->s__img_tratar[$i]['nombre'] = $datos['nombre_imagen'].'/mod2'.'/'.$this->s__fila_acciones['nombre_imagen'];
                    	break;
                	}                      
            	}
        	}
        }else{
            if (!is_null($datos['nombre_imagen'])) {
                $this->get_relacion()->tabla('imagenes_acciones')->set($datos); 
            }
        }
        $this->get_relacion()->tabla('acciones')->set($datos);
        $this->get_relacion()->tabla('imagenes_acciones')->resetear_cursor();
        $this->get_relacion()->tabla('acciones')->resetear_cursor();
    }

    function evt__frm_nuevo_estado__cancelar()
    {
    	$this->get_relacion()->tabla('acciones')->resetear_cursor();
    }

    
}
?>