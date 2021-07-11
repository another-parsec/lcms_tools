function GET_ATTR(str, attr, type){

 var st_ix = str.indexOf(attr) + attr.length;

 var new_str = str.substring(str.indexOf('"', st_ix) + 1, str.indexOf('"', st_ix + 2));
	
	if(type == undefined) return new_str;
	if(type[0] == "i") return parseInt(new_str);
	if(type[0] == "f") return parseFloat(new_str);
}

function GET_TAG_VAL(str, tag){

 var st_ix = str.indexOf("<" + tag) + tag.length;
 return str.substring(str.indexOf('>', st_ix) + 1, str.indexOf("</" + tag + ">"));
}

function GET_POINTS_FROM_32B_DATA(data){
    
     try{

		var str = atob(data);
		var n = str.length;
	}
	catch(error) {
		
		return null;
	}
    
    var view = new DataView(new ArrayBuffer(n));
	for(var i = 0; i < n; i++)  view.setUint8(i, str.charCodeAt(i));
    
    var pts = [];
    
    for(i = 0; i < n; i+=8) pts.push(['',view.getFloat32(i),view.getFloat32(i + 4)]);
    
    return pts;
}

function GET_POINTS_FROM_64B_DATA(data){
    
    try{

		var str = atob(data);
		var n = str.length;
	}
	catch(error) {
		
		return null;
	}
    
    var view = new DataView(new ArrayBuffer(n));
	for(var i = 0; i < n; i++)  view.setUint8(i, str.charCodeAt(i));
    
    var pts = [];
    
    for(i = 0; i < n; i+=16) pts.push(['',view.getFloat64(i),view.getFloat64(i + 8)]);
    
    return pts;
}






self.addEventListener("message", function(e){

	if(e.data.action == "process") PARSE_MZXML_STR(e.data.file_text, e.data.filters);
		
})



function PARSE_MZXML_STR(mz_xml_str, filters){
	
	var scan_strs = mz_xml_str.split("</scan>");
    
    var enc = GET_ATTR(scan_strs[0],'precision');
    var ms1_only = filters.indexOf("ms1_only") > -1; 
    
    
    var i = 0, n = scan_strs.length;
    
    if(enc == 32){
        
        for(;i < n; i++){
            
            var s = scan_strs[i];
            
            var ms_level = GET_ATTR(s,'msLevel', "int");
            if(ms1_only && ms_level > 1) continue;
            
            var scan_data = {};    
            scan_data.ms_level = ms_level
            scan_data.index = GET_ATTR(s, "num", "int");
            scan_data.polarity = GET_ATTR(s,'polarity') == "-" ? -1: 1;
            var rt = GET_ATTR(s,'retentionTime');
            scan_data.time = parseFloat(rt.substring(2, rt.length -1));
            scan_data.tic = GET_ATTR(s,'totIonCurrent', "float");
            scan_data.points =  GET_POINTS_FROM_32B_DATA(GET_TAG_VAL(s, "peaks"));
            
            self.postMessage({progress: (i + 1.0)/n, result:scan_data});
        }
        
    }
    else{
        
         for(;i < n; i++){
            
            var s = scan_strs[i];
            
            var ms_level = GET_ATTR(s,'msLevel', "int");
            if(ms1_only && ms_level > 1) continue;
            
            var scan_data = {};    
            scan_data.ms_level = ms_level
            scan_data.index = GET_ATTR(s, "num", "int");
            scan_data.polarity = GET_ATTR(s,'polarity') == "-" ? -1: 1;
            var rt = GET_ATTR(s,'retentionTime');
            scan_data.time = parseFloat(rt.substring(2, rt.length -1));
            scan_data.tic = GET_ATTR(s,'totIonCurrent', "float");
            scan_data.points =  GET_POINTS_FROM_64B_DATA(GET_TAG_VAL(s, "peaks"));
             
            self.postMessage({progress: (i + 1.0)/n, result:scan_data});
        }
        
    }
	
	self.close();
}