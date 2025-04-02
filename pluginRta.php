<?php 
class AutoResponderPlugin extends MantisPlugin {
    public function register() {
        $this->name = 'Auto Responder';
        $this->description = 'Plugin == Envía una nota automática en incidencias nuevas de categoría "Error" con retraso de una hora.';
        $this->version = '1.0';
        $this->author = 'Sergio Quiroga';
    }

    public function hooks() {
        return array(
            'EVENT_REPORT_BUG' => 'agregar_nota_automatica',
            'EVENT_CORE_READY' => 'verificar_respuestas_pendientes'
        );
    }

    function agregar_nota_automatica($p_evento, $p_incidencia) {
        if ($p_incidencia->category_id == $this->obtener_id_categoria('Error')) {
            $bug_id = $p_incidencia->id;
            $mensaje = "Ya estamos atendiendo tu caso. Gracias por reportarlo.";
            
            $this->programar_respuesta($bug_id, $mensaje);
        }
    }
    
    private function programar_respuesta($bug_id, $mensaje) {
        $ruta_datos = $this->get_data_directory();
        if (!file_exists($ruta_datos)) {
            mkdir($ruta_datos, 0755, true);
        }
        
        $tiempo_envio = time() + 120; // Una hora después
        $datos = array(
            'bug_id' => $bug_id,
            'mensaje' => $mensaje,
            'tiempo_envio' => $tiempo_envio
        );
        
        $archivo_respuestas = $ruta_datos . '/respuestas_pendientes.php';
        $respuestas = array();
        
        if (file_exists($archivo_respuestas)) {
            include($archivo_respuestas);
        }
        
        $respuestas[] = $datos;
        
        file_put_contents($archivo_respuestas, '<?php $respuestas = ' . var_export($respuestas, true) . '; ?>');
    }
    
    function verificar_respuestas_pendientes() {
        $ruta_datos = $this->get_data_directory();
        $archivo_respuestas = $ruta_datos . '/respuestas_pendientes.php';
        
        if (!file_exists($archivo_respuestas)) {
            return;
        }
        
        $respuestas = array();
        include($archivo_respuestas);
        
        $tiempo_actual = time();
        $respuestas_pendientes = array();
        
        // Obtener el ID del usuario soporte_koncilia
        $usuario_soporte = user_get_id_by_name('soporte_koncilia');
        
        foreach ($respuestas as $respuesta) {
            if ($respuesta['tiempo_envio'] <= $tiempo_actual) {
                // Verificar si ya existe una nota del usuario soporte_koncilia
                $notas = bugnote_get_all_visible($respuesta['bug_id']);
                $nota_existente = false;
                
                foreach ($notas as $nota) {
                    if ($nota->reporter_id == $usuario_soporte) {
                        $nota_existente = true;
                        break;
                    }
                }
                
                if (!$nota_existente) {
                    bugnote_add(
                        $respuesta['bug_id'],
                        $respuesta['mensaje'],
                        0, // time tracking
                        false, // private
                        BUGNOTE,
                        '', // note type
                        $usuario_soporte // ID del usuario que enviará la nota
                    );
                }
            } else {
                $respuestas_pendientes[] = $respuesta;
            }
        }
        
        file_put_contents($archivo_respuestas, '<?php $respuestas = ' . var_export($respuestas_pendientes, true) . '; ?>');
    }

    private function obtener_id_categoria($nombre_categoria) {
        $t_categorias = category_get_all_rows($p_project_id = ALL_PROJECTS);
        foreach ($t_categorias as $t_categoria) {
            if ($t_categoria['name'] == $nombre_categoria) {
                return $t_categoria['id'];
            }
        }
        return null;
    }
    
    private function get_data_directory() {
        return config_get_global('plugin_path') . $this->basename . '/data';
    }
    
    public function schema() {
        return array();
    }
}
?>