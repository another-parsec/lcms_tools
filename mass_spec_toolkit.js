function search(array, value, comparator){

	var left = 0, right = array.length -1, i;
	
	while((right - left) > 1){
		
		i = Math.floor(0.5*(left + right));
		
		if(comparator(array[i], value) > 0) left = i;
		else right = i;
	}
	
	if((right - left) == 1){
		
		if(comparator(array[left], value) + comparator(array[right], value) < 0) return left;
	
		return right;
	}
	
	return left;
}

var COMPARATOR_MZ = function(v1, v2){
			
	return v2[1] - v1[1];
}
var COMPARATOR_TIME = function(v1, v2){
			
	return v2[0] - v1[0];
}




function ARRAY(val, length){
	
	var a = [];
	
	for(var i = 0; i < length; i++) a.push(val);
	
	return a;
}

function MEDIAN(array){
	
	array.sort( function(a,b) {return a - b;} );

    var half = Math.floor(array.length/2);

    if(array.length % 2) return array[half];

    return (array[half-1] + array[half]) / 2.0;
}

function MOST_INTENSE_POINT(pts){
	
	var max_index = 0;
	var max_int = pts[0][2];
	
	for(var i = 1; i < pts.length; i++){
	
		if(pts[i][2] > max_int){
			
			max_index = i;
			max_int = pts[i][2];
		}
		
	}
	
	return pts[max_index];
}











function MZRange(mz, ppm){
	
	this.mz = mz;
	this.ppm = ppm;
	
	this.setWidth = function(p){
		
		this.ppm = p;
	}
	
	this.min = function(){
		
		return this.mz*(1 - this.ppm*0.000001);
	}
	
	this.max = function(){
		
		return this.mz*(1 + this.ppm*0.000001);
	}
	
	this.containsMZ = function(mz){
		
		return mz >= this.min() && mz <= this.max();
	}
	
	this.containsPoint = function(pt){
		
		return this.containsMZ(pt[1]);
	}
}

function Scan(data){
    
    
    this.polarity = data.polarity;
	this.ms_level = data.ms_level;
	this.parent_ion_mz = null;
	this.index = data.index;
	this.time = data.time;	
	this.points = data.points;
}

Scan.prototype.getPolarity = function(){

	return this.polarity;
}

Scan.prototype.getMSLevel = function(){

	return this.ms_level;
}

Scan.prototype.getParentIonMZ = function(){

	return this.parent_ion_mz;
}

Scan.prototype.getTime = function(){

	return this.time;
}

Scan.prototype.getIndex = function(){

	return this.index;
}

Scan.prototype.getTIC = function(){

	var sum = 0;
	for(var dp of this.points){

		sum += dp[2]; 
	}

	return sum;
}

Scan.prototype.numberOfPoints = function(){

	return this.points.length;
}

Scan.prototype.rangeMZ = function(){

	return {min:this.points[0][1], max:this.points[this.points.length-1][1]};
}


Scan.prototype.maxIntensity = function(){

	var max = this.points[0][2];
	for(var dp of this.points){

		if(dp[2] > max) max = dp[2]; 
	}

	return max;
}

Scan.prototype.minIntensity = function(){

	var min = this.points[0][2];
	for(var dp of this.points){

		if(dp[2] < min) min = dp[2]; 
	}

	return min;
}


Scan.prototype.removePointsBelowIntensity = function(i){

	 var new_points = this.points.filter(function(p){

		 return p[2] > i;
	 });

	this.points = new_points;
}

Scan.prototype.pointAtIndex = function(index){

	return this.points[index];
}

Scan.prototype.pointClosestToToMZ = function(mz){

	var index = search(this.points, {1:mz}, COMPARATOR_MZ);

	return this.points[index];
}


Scan.prototype.indexRangeOfPointsInMZRange = function(mz_range){

	var pts = this.points;

	var left_i = search(pts, {1:mz_range.min()}, COMPARATOR_MZ);
	var right_i = search(pts, {1:mz_range.max()}, COMPARATOR_MZ);
    
    
    for(var i = left_i; i <=right_i; i++){
        
        
        if(mz_range.containsMZ(pts[i][1]) == false){
            
            if(i == left_i){
                
                left_i++;
                if(left_i > right_i) return null;
            }
            else{
                
                right_i = i-1;
                if(left_i > right_i) return null;
                break;
            }
        }
        
    }

	return {min:left_i, max:right_i};
}


Scan.prototype.pointsInIndexRange = function(range){
    
    if (range == null) return [];

	var subset = [];

	for(var i = range.min; i <= range.max; i++) subset.push(this.points[i]);

	return subset;
}

Scan.prototype.pointsInMZRange = function(mz_range){

	var r = this.indexRangeOfPointsInMZRange(mz_range);
	return this.pointsInIndexRange(r);
}


Scan.prototype.matchesScan = function(scan){

	if(this.getPolarity() != scan.getPolarity()) return false;
	if(this.getMSLevel() != scan.getMSLevel()) return false;

	var r1 = this.rangeMZ();
	var r2 = scan.rangeMZ();

	//if(Math.abs(r1.min - r2.min) > 4) return false;
	//if(Math.abs(r1.max - r2.max) > 4) return false;

	return true;
}
	




function ScanSeries(){
	
	this.scans = [];
}
	
ScanSeries.prototype.addScan = function(scan){

	if(this.scans.length == 0){
		
		this.scans.push(scan);

		this.pol = scan.getPolarity();
		this.ms_level = scan.getMSLevel();
		this.mz_range = scan.rangeMZ();

		return true;
	} 

	if(scan.matchesScan(this.scans[this.scans.length - 1])){
		
		this.scans.push(scan);
		return true;
	}

	return false;
}

ScanSeries.prototype.polarity = function(){

	return this.pol;
}

ScanSeries.prototype.msLevel = function(){

	return this.ms_level;
}

ScanSeries.prototype.timeSpan = function(){

	return {start:this.scans[0].time, end:this.scans[this.scans.length - 1].time};
}

ScanSeries.prototype.rangeMZ = function(){

	return this.mz_range;
}

ScanSeries.prototype.numberOfScans = function(){

	return this.scans.length;
}

ScanSeries.prototype.setName = function(name){

	this.name = name;
}

ScanSeries.prototype.extractChromatogram = function(mz_range){
    
    var new_chromatogram = new Chromatogram();
    
    for(var scan_index = 0, n = this.scans.length; scan_index < n; scan_index++){

        var scan = this.scans[scan_index];
        var pts = scan.pointsInMZRange(mz_range);
        
        
        if(pts.length > 0){
            
            var pt = MOST_INTENSE_POINT(pts);
            new_chromatogram.addDataPoint([scan.time, pt[1], pt[2]]);   
        }
        else{
            
            new_chromatogram.addDataPoint([scan.time, mz_range.mz, 0]);
        }
    }
    
    return new_chromatogram;
}

ScanSeries.prototype.extractChromatograms = function (params){

	var pmx = [], n = this.scans.length, chromatograms = [];

	for(var s of this.scans) pmx.push(ARRAY(1, s.numberOfPoints()));


	for(var scan_index = 0; scan_index < n; scan_index++){

		var scan = this.scans[scan_index];


		for(var i = 0, n2 = scan.numberOfPoints(); i < n2; i++){

			if(pmx[scan_index][i]){


				var point = scan.pointAtIndex(i);
				var ix_R = scan.indexRangeOfPointsInMZRange(new MZRange(point[1], params.ppm));
				ix_R.min = i;

				for(var x = ix_R.min; x <= ix_R.max; x++) pmx[s][x] = 0;


				point = MOST_INTENSE_POINT(scan.pointsInIndexRange(ix_R))
				var mz_R = new MZRange(point[1], params.ppm);


				var new_chromatogram = new Chromatogram();
				new_chromatogram.addDataPoint(point);


				var max_span_count = 0, span_count  = 0;

				for(var scan2_index = s; scan2_index < n; scan2_index++){

					var future_scan = this.scans[scan2_index];
					var ix_R = future_scan.indexRangeOfPointsInMZRange(mz_R);
					var pts = future_scan.pointsInIndexRange(ix_R);

					if(pts.length > 0){

						for(var x = ix_R.min; x <= ix_R.max; x++) pmx[scan2_index][x] = 0;

						new_chromatogram.addDataPoint(MOST_INTENSE_POINT(pts));

						max_span_count++;

					}else{

						if(span_count > max_span_count) max_span_count = span_count;

						span_count = 0;
					}
				}



				if(max_span_count < 5) continue;

				var ints = new_chromatogram.intensityDistributionOverSpan();
				var max = ints[ints.length -1];
				var median =  ints[Math.floor(0.5*ints.length)];

				if(max < params.min_intensity) continue;
				if(max/median < params.min_SN) continue;


				chromatograms.push(new_chromatogram);
			}
		}		
	}

	chromatograms.sort(function(a, b){ return b.MZ() - a.MZ();});
	return chromatograms;
}






function Chromatogram(){
	
	this.points = [];
	this.mz_val = null;
}
	
Chromatogram.prototype.addDataPoint = function (p){
		
		this.points.push(p);
		this.mz_val = null;
	}
	
Chromatogram.prototype.MZ = function(){
		
		if(this.mz_val == null){
			
			var mz_vals = [];

			for(var i = 0, n = this.points.length; i <= n; i++){

				mz_vals.push(this.points[i][1]);
			}
			
			this.mz_val = MEDIAN(mz_vals);
		}
		
		return this.mz_val;
	}
	
Chromatogram.prototype.span = function(){
		
		return {start:this.points[0][0], end:this.points[this.points.length -1][0]};
	}

Chromatogram.prototype.numberOfPoints = function(){
		
		return this.points.length;
	}
	
Chromatogram.prototype.pointsInIndexRange = function(range){
		
		var subset = [];
		
		for(var i = range.min; i <= range.max; i++) subset.push(this.points[i]);
		
		return subset;
	}
	
Chromatogram.prototype.indexRangeOfPointsInSpan = function(span){
		
		if(span == undefined) return {min:0, max:this.points.length - 1};
		
		var left_i = search(this.points, {0:span.start}, COMPARATOR_TIME);
		var right_i = search(this.points, {0:span.end}, COMPARATOR_TIME);
		
		if(span.start > this.points[left_i][0]) left_i++;
		if(span.end < this.points[right_i][0]) right_i--;
		
		return {min:left_i, max:right_i};
	}
	
Chromatogram.prototype.pointsInSpan = function(span){
		
		var r = this.indexRangeOfPointsInSpan(span);
		return this.pointsInIndexRange(r);
	}
	
Chromatogram.prototype.mostIntensePointInSpan = function(span){
			
		var index_range = this.indexRangeOfPointsInSpan(span);
		
		var max = this.points[index_range.min][2];
		var max_i = index_range.min;
		for(var i = index_range.min + 1; i <= index_range.max; i++){
			
			var dp = this.points[i];
			if(dp[2] > max) {
				
				max = dp[2]; 
				max_i = i;
			}
		}
		
		return this.points[max_i];
	}
	
Chromatogram.prototype.medianIntensityOverSpan = function(span){
		
		var index_range = this.indexRangeOfPointsInSpan(span);
		
		var intensities = [];
		
		for(var i = index_range.min; i <= index_range.max; i++){
			
			intensities.push(this.points[i][2]);
		}
		
		return MEDIAN(intensities);
	}
	
Chromatogram.prototype.intensityDistributionOverSpan = function(span){
		
		var index_range = this.indexRangeOfPointsInSpan(span);
		
		var intensities = [];
		
		for(var i = index_range.min; i <= index_range.max; i++){
			
			intensities.push(this.points[i][2]);
		}
		
		intensities.sort(function(a,b) {return a - b;} );
		
		return intensities;
	}
	
Chromatogram.prototype.subChromatogramFromSpan = function(span){
		
		var new_chromatogram = new Chromatogram();
		
		var pts = this.pointsInSpan(span);
		for(var pt of pts){
			
			new_chromatogram.addDataPoint(pt);
		}
		
		return new_chromatogram;
	}
	

Chromatogram.prototype.areaUnderSpan = function(span){
		
		var area = 0;
		
		var pts = this.pointsInSpan(span);
		
		for(var i = 1; i < pts.length; i++){
			
			var dt = pts[i][0] - pts[i-1][0];
			area += 0.5*dt*(pts[i][2] - pts[i-1][2])
		}
		
		return area;
	}
		
Chromatogram.prototype.smooth = function(){
    
    
    
    
}

	
Chromatogram.prototype.extractPeaks = function(params){
		
	
}

