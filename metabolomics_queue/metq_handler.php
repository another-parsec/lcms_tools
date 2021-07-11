<?php
	 date_default_timezone_set('America/Los_Angeles');
     define('KB', 1024);

	function ECHO_RESPONSE($success, $info, $data = array()){
        
        $RESULT_ARRAY = array();
        $RESULT_ARRAY['success'] = $success;
        $RESULT_ARRAY['info'] = $info;
        $RESULT_ARRAY['data'] = $data;
        
        header('Content-Type: application/json');
        echo json_encode($RESULT_ARRAY);
        exit();   
    }

	function ARRAY_HAS_VALUES_FOR_KEYS($array, $keys){

		foreach($keys as $key){

			if(array_key_exists($key, $array) == false) return false;
		}

		return true;
	}

	function NEXT_RANDOM_EDIT_KEY($len = 20){
      
      $charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
      $base = strlen($charset);
      $result = '';
        
      for($i = 0; $i < $len; $i++) $result .= $charset[rand(0, $base-1)];
      return $result;
    }

	function DELETE_SAMPLE_SET($id){
		
		global $DB_CONNECTION;
        global $QUEUE_TABLE_NAME;
		
		try{
	
			$QUERY = $DB_CONNECTION->prepare("DELETE FROM $QUEUE_TABLE_NAME WHERE id = :id AND run = 0");
			$QUERY->bindParam(':id', $id, PDO::PARAM_INT); 
			$QUERY->execute();
		}
		catch(PDOException $e){

			return false;
		}
		
		return true;
	}


    
	if(!isset($_POST['action'])) ECHO_RESPONSE(false, 'missing_info_error');
	$ACTION = $_POST['action'];


	$SERVER_NAME = "localhost";
    $USERNAME = "USERNAME";
    $PASSWORD = "PASSWORD";
    $GLOBAL_EDIT_KEY = '';


    $QUEUE_TABLE_NAME = 'metabolomics_run_queue';

    $DB_CONNECTION = null;
    try {

       $DB_CONNECTION = new PDO("mysql:host=$SERVER_NAME;dbname=labdata", $USERNAME, $PASSWORD);
    }
    catch(PDOException $e){
        
        ECHO_RESPONSE(false, 'database_error');
    }
	
	
	if($ACTION === 'addnewsampleset'){
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key', 'data')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		$EDIT_KEY = $_POST['edit_key'];
		$DATA = json_decode($_POST['data'], true);
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($DATA, array('lab_name', 'submitter_name', 'num_samples', 'description')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		if(!isset($_FILES['sample_datasheet']))  ECHO_RESPONSE(false, 'missing_info_error');
		
		$DESTINATION_FOLDER = 'sampleset_datasheets/';
		$FINAL_FILE_URL = '';
                
        $SDF = $_FILES['sample_datasheet'];
		$FILE_EXT = explode('.', $SDF['name']);
            
        if(end($FILE_EXT) === 'csv' && $SDF['size'] < 16*KB) {
                
			$name = preg_replace('/[^a-zA-Z0-9]+/', '', strtolower($DATA['submitter_name']));

			$NEW_FILE_NAME = 'metabo_datasheet_'.$name.'_'.$DATA['num_samples'].'_'.date('Ymd') ;
            
            $FINAL_FILE_URL = $DESTINATION_FOLDER.$NEW_FILE_NAME.'.csv';
            
            $i = 2;
            
            while(file_exists($FINAL_FILE_URL)){
                
                $FINAL_FILE_URL = $DESTINATION_FOLDER.$NEW_FILE_NAME.'-S'. $i.'.csv';
                $i++;
            }

			if(!move_uploaded_file($SDF['tmp_name'],  $FINAL_FILE_URL)){

				ECHO_RESPONSE(false, 'file_upload_error');
			}
        }
		else{
			
			ECHO_RESPONSE(false, 'file_upload_error');
		}
		
		try{
           
            $QUERY = $DB_CONNECTION->prepare("SELECT MAX(position) AS position FROM $QUEUE_TABLE_NAME WHERE run = 0");
            $QUERY->execute();
            $result = $QUERY->fetch(PDO::FETCH_ASSOC);
            $NEW_SAMPLE_SET_POSITION = intval($result['position']) + 1;
			

            $QUERY = $DB_CONNECTION->prepare("INSERT INTO `$QUEUE_TABLE_NAME` (`date_created`, `num_samples`, `lab_name`, `submitter_name`, `description`, `edit_key`, `position`, `sample_datasheet_url`) VALUES (:dc, :ns, :ln, :sn, :d, :ek, :p, :sdu)");

			$CREATION_TIME = date('Y-m-d H:i:s');
            $QUERY->bindParam(':dc', $CREATION_TIME, PDO::PARAM_STR); 
            $QUERY->bindParam(':ns', $DATA['num_samples'], PDO::PARAM_INT); 
			$QUERY->bindParam(':ln', $DATA['lab_name'], PDO::PARAM_STR); 
            $QUERY->bindParam(':sn', $DATA['submitter_name'], PDO::PARAM_STR); 
            $QUERY->bindParam(':d', $DATA['description'], PDO::PARAM_STR); 
            $QUERY->bindParam(':ek', $EDIT_KEY, PDO::PARAM_STR); 
            $QUERY->bindParam(':p', $NEW_SAMPLE_SET_POSITION, PDO::PARAM_STR); 
            $QUERY->bindParam(':sdu', $FINAL_FILE_URL, PDO::PARAM_STR); 
            $QUERY->execute();
        }
        catch(PDOException $e){
            
            ECHO_RESPONSE(false, 'database_error');
        }
        
        ECHO_RESPONSE(true, 'new_sample_set_was_added');
	}
	else if($ACTION === 'deletesamplesets'){
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key', 'data')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		$EDIT_KEY = $_POST['edit_key'];
		$DATA = json_decode($_POST['data'], true);
		
		
		if($EDIT_KEY == $GLOBAL_EDIT_KEY){
			
			foreach($DATA as $SAMPLE_SET_ID){
				
				if(DELETE_SAMPLE_SET($SAMPLE_SET_ID) == false) ECHO_RESPONSE(false, 'database_error');
			}
		}
		else{
			
			foreach($DATA as $SAMPLE_SET_ID){
				
				$QUERY = $DB_CONNECTION->prepare("SELECT edit_key, sample_datasheet_url FROM $QUEUE_TABLE_NAME WHERE id = :id");
				$QUERY->bindParam(':id', $SAMPLE_SET_ID, PDO::PARAM_INT); 
				$QUERY->execute();
				
				$RESULT = $QUERY->fetch();
				
				if($EDIT_KEY == $RESULT['edit_key']){
					
					if(DELETE_SAMPLE_SET($SAMPLE_SET_ID) == false) ECHO_RESPONSE(false, 'database_error');
					
					if($RESULT['sample_datasheet_url'] != null){
					    
					    unlink($RESULT['sample_datasheet_url']);
					}
				}
			}
		}
		
		ECHO_RESPONSE(true, 'all_sample_sets_were_deleted');
	}
	else if($ACTION === 'movesamplesets'){
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key', 'data')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		$EDIT_KEY = $_POST['edit_key'];
		$DATA = json_decode($_POST['data'], true);
		
		if($EDIT_KEY != $GLOBAL_EDIT_KEY)  ECHO_RESPONSE(false, 'permission_error');
		
		foreach($DATA as $SAMPLE_SET_DATA){
			
			try{
	
				$QUERY = $DB_CONNECTION->prepare("UPDATE $QUEUE_TABLE_NAME SET `position` = :p WHERE id = :id AND run = 0");
				$QUERY->bindParam(':p', $SAMPLE_SET_DATA["position"], PDO::PARAM_INT); 
				$QUERY->bindParam(':id', $SAMPLE_SET_DATA["id"], PDO::PARAM_INT); 
				$QUERY->execute();
			}
			catch(PDOException $e){

				 ECHO_RESPONSE(false, 'database_error');
			}
		}
		
		ECHO_RESPONSE(true, 'sample_sets_were_moved');
	}
    else if($ACTION === 'setrundatesforsamplesets'){
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key', 'data')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		$EDIT_KEY = $_POST['edit_key'];
		$DATA = json_decode($_POST['data'], true);
		
		if($EDIT_KEY != $GLOBAL_EDIT_KEY)  ECHO_RESPONSE(false, 'permission_error');
		
		foreach($DATA as $SAMPLE_SET_DATA){
			
			try{
	
				$QUERY = $DB_CONNECTION->prepare("UPDATE $QUEUE_TABLE_NAME SET `run` = 1, `date_run` = :dr WHERE id = :id");
				$DATE_RUN = date('Y-m-d H:i:s',strtotime($SAMPLE_SET_DATA["date_run"]));
				$QUERY->bindParam(':dr', $DATE_RUN, PDO::PARAM_STR); 
				$QUERY->bindParam(':id', $SAMPLE_SET_DATA["id"], PDO::PARAM_INT); 
				$QUERY->execute();
			}
			catch(PDOException $e){

				 ECHO_RESPONSE(false, 'database_error');
			}
		}
		
		ECHO_RESPONSE(true, 'sample_set_runtimes_were_updated');
	}
	else if($ACTION === 'getsamplesets'){
		
		if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key', 'order', 'wasrun')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
		
		$EDIT_KEY = $_POST['edit_key'];
		$ORDER = $_POST['order'];
		
		try{
			
			$WAS_RUN = $_POST['wasrun'] == "true" ? 1 : 0;
	
			$orderby = 'position';
			if($ORDER === 'daterun') $orderby = 'date_run';
			
			$QUERY = $DB_CONNECTION->prepare("SELECT * FROM $QUEUE_TABLE_NAME WHERE run = $WAS_RUN ORDER BY $orderby");
        	$QUERY->execute();
			$SAMPLE_SETS= $QUERY->fetchAll();
		
			$SAMPLE_SETS_TO_RETURN = array();
        
			foreach($SAMPLE_SETS as $USS){

				 $NEW_SAMPLE_SET = array();
				 $NEW_SAMPLE_SET['id'] = $USS['id'];
				 $NEW_SAMPLE_SET['date_created'] = date('m / d / Y', strtotime($USS['date_created']));
			     $NEW_SAMPLE_SET['days_in_queue'] = round((time() - strtotime($USS['date_created']))/(60 * 60 * 24));
				 $NEW_SAMPLE_SET['num_samples'] = $USS['num_samples'];
				 $NEW_SAMPLE_SET['lab_name'] = $USS['lab_name'];
				 $NEW_SAMPLE_SET['submitter_name'] = $USS['submitter_name'];
				 $NEW_SAMPLE_SET['description'] = $USS['description'];
				 $NEW_SAMPLE_SET['position'] = $USS['position'];
				 $NEW_SAMPLE_SET['sample_datasheet_url'] = $USS['sample_datasheet_url'];
				
				 $NEW_SAMPLE_SET['editable'] = false;

				 if($EDIT_KEY == $GLOBAL_EDIT_KEY && $WAS_RUN == 0){

					 $NEW_SAMPLE_SET['editable'] = true;                 
				 }
				 else if($EDIT_KEY == $USS['edit_key'] && $WAS_RUN == 0){

					$NEW_SAMPLE_SET['editable'] = true;
				 }
                
                foreach($NEW_SAMPLE_SET as $key => $val){
                    
                     $NEW_SAMPLE_SET[$key] = htmlentities($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
				array_push($SAMPLE_SETS_TO_RETURN, $NEW_SAMPLE_SET);
			}

			ECHO_RESPONSE(true, 'sample_sets_retrieved', $SAMPLE_SETS_TO_RETURN);
		}
		catch(PDOException $e){
            
            ECHO_RESPONSE(FALSE, 'database_error');
        }
	}
    else if($ACTION === 'getstats'){
        
        $STATS = array();
        
        try{
		
			$QUERY = $DB_CONNECTION->prepare("SELECT SUM(num_samples) AS count FROM $QUEUE_TABLE_NAME WHERE run = 1");
			$QUERY->execute();
			$STATS['num_samples_run'] = intval($QUERY->fetch()['count']);
            
            $QUERY = $DB_CONNECTION->prepare("SELECT SUM(num_samples) AS count FROM $QUEUE_TABLE_NAME WHERE run = 0");
			$QUERY->execute();
			$STATS['num_samples_not_yet_run'] = intval($QUERY->fetch()['count']);
            
            
            $QUERY = $DB_CONNECTION->prepare("SELECT num_samples, GREATEST(date_run, date_created) AS date, run FROM $QUEUE_TABLE_NAME ORDER BY 1 DESC");
			$QUERY->execute();
            $RESULTS = $QUERY->fetchAll();
            
            
            $LAST_ACTIONS = array();
            
            foreach($RESULTS as $RESULT){
                
                $change = $RESULT['run'] == 0 ? intval($RESULT['num_samples']) : -1*intval($RESULT['num_samples']);
                array_push($LAST_ACTIONS, array('date' => $RESULT['date'], 'change' => $change));
            }
            
			$STATS['action_history'] = $LAST_ACTIONS;
            
			ECHO_RESPONSE(true, 'stats_retrieved', $STATS);
		}
		catch(PDOException $e){
            
            ECHO_RESPONSE(false, 'database_error');
        }
    }
    else if($ACTION === 'getuserstatus'){
        
        if(ARRAY_HAS_VALUES_FOR_KEYS($_POST, array('edit_key')) == false){
            
            ECHO_RESPONSE(false, 'missing_info_error');
        }
        $EDIT_KEY = $_POST['edit_key'] === 'none' ? NEXT_RANDOM_EDIT_KEY() : $_POST['edit_key'];
        
        $STATUS = 'normal_user';
        if($EDIT_KEY === $GLOBAL_EDIT_KEY) $STATUS = 'admin';
        
        try{
		
			ECHO_RESPONSE(true, 'status_retrieved', array("status" => $STATUS, "edit_key" => $EDIT_KEY));
		}
		catch(PDOException $e){
            
            ECHO_RESPONSE(false, 'database_error');
        }
    }

	ECHO_RESPONSE(false, 'invalid_action_error');
?>
