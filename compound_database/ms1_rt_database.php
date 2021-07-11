<?php

 function RETURN_RESPONSE($code, $info, $data = array()){
        
    $RESULT_ARRAY = array();
    $RESULT_ARRAY["success_code"] = $code;
    $RESULT_ARRAY["info"] = $info;
    $RESULT_ARRAY["data"] = $data;
    
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



$SUBMITTED_QUERY = json_decode($_POST["query"], true);

$TABLE_NAME = null;
$ACTION = null;

if(ARRAY_HAS_VALUES_FOR_KEYS($SUBMITTED_QUERY, array("database_name", "action")) == false){
            
    RETURN_RESPONSE(0, "missing_param");
}
else{
    
    if($SUBMITTED_QUERY["database_name"] == "zic_philic"){
        
         $TABLE_NAME = "zic_philic_master_ms1_rt_table";
    }
    else if($SUBMITTED_QUERY["database_name"] == "luna_nh2"){
        
         $TABLE_NAME = "luna_nh2_master_ms1_rt_table";
    }
    
    if($SUBMITTED_QUERY["action"] == "retrieve_rows"){
        
         $ACTION = "retrieve_rows";
    }
    else if ($SUBMITTED_QUERY["action"] == "add_compound"){
        
         $ACTION = "add_compound";
    }
}

$SERVER_NAME = "localhost";
$USERNAME = "";
$PASSWORD = "";

$DB_CONNECTION = null;
try {

    $DB_CONNECTION = new PDO("mysql:host=$SERVER_NAME;dbname=labdata", $USERNAME, $PASSWORD);
}
catch(PDOException $e){

    RETURN_RESPONSE(0, "connection_failed: " + $e->getMessage());
}


if($ACTION == "retrieve_rows"){

     try{

        $ROWS = array();
        $query = $DB_CONNECTION->prepare("SELECT * FROM $TABLE_NAME");
        $query->execute();
        $results = $query->fetchAll();
        foreach($results as $row){

            $new_row = array();
            $new_row["name"] = $row["name"];
            $new_row["formula"] = $row["formula"];
            $new_row["dominant_ion_mode"] = $row["dominant_ion_mode"];
            $new_row["positive_mz"] = $row["positive_mz"];
            $new_row["negative_mz"] = $row["negative_mz"];
            $new_row["rt"] = $row["rt"];
            $new_row["rt_verified_by_std"] = $row["rt_verified_by_std"];
            $new_row["pubchem_id"] = $row["pubchem_id"];
            array_push($ROWS, $new_row);
        }

        RETURN_RESPONSE(1, "database_rows_were_retrieved", $ROWS);
    }
    catch(PDOException $e){

        RETURN_RESPONSE(0, "database_error: " + $e->getMessage(), array());
    }
}
else if ($ACTION == "add_compound"){
    
    $DATA = $SUBMITTED_QUERY["data"];

    if(ARRAY_HAS_VALUES_FOR_KEYS($DATA, array("ion_mode", "name", "formula", "pos_mz", "neg_mz", "rt", "rt_verified")) == false){
            
        RETURN_RESPONSE(0, "missing_data_for_add_compound_action");
    }
    try{
        
        $query = $DB_CONNECTION->prepare("INSERT INTO $TABLE_NAME (`date_added`,`name`, `formula`, `dominant_ion_mode`, `positive_mz`, `negative_mz`, `rt`, `rt_verified_by_std`, `pubchem_id`) VALUES (:d,:n, :f, :i, :pmz, :nmz, :rt, :rtv, :pc)");

        $date = date('Y-m-d H:i:s');
        $query->bindParam(":d", $date, PDO::PARAM_STR); 
        $query->bindParam(":n", $DATA["name"], PDO::PARAM_STR); 
        $query->bindParam(":f", $DATA["formula"], PDO::PARAM_STR); 
        $query->bindParam(":i", $DATA["ion_mode"], PDO::PARAM_STR); 
        $query->bindParam(":pmz", $DATA["pos_mz"], PDO::PARAM_STR); 
        $query->bindParam(":nmz", $DATA["neg_mz"], PDO::PARAM_STR); 
        $query->bindParam(":rt", $DATA["rt"], PDO::PARAM_STR); 
        $query->bindParam(":rtv", $DATA["rt_verified"], PDO::PARAM_STR); 
        $query->bindParam(":pc", $DATA["pubchem_id"], PDO::PARAM_STR); 

        $query->execute();
    }
    catch(PDOException $e){

        RETURN_RESPONSE(0, "database_error_occured: ".$e->getMessage());
    }
    
    RETURN_RESPONSE(1, "compound_added");
}

RETURN_RESPONSE(0, "query_unclear");
?>