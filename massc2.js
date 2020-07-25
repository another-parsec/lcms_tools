var MASS_LIST = {
"H":1.007825,
"H*":2.014102,
"B":11.009305,
"C":12.000000, 
"C*":13.003355, 
"Cl":34.968853,
"N":14.003074,
"N*":15.000109,
"O":15.994915,
"F":18.998403,
"P":30.973763,
"S":31.972072,
"U":22.989770,
"Br":78.918336,
"I":126.904477
}

var PROTON_MASS = 1.00727647;
var C_NEUTRON_MASS = MASS_LIST["C*"] - MASS_LIST["C"];

function islowercase(c){
	
	return (c>="a" && c<="z")
}

function isuppercase(c){
	
	return c>="A" && c<="Z";
}

function isdigit(c) {
 
	return c >= "0" && c <= "9";
}

function isspecial(c) {

	return c == "-" || c == "+";
}

function formula_from_str(s){
	
	var f = {};
	var e_str = '';
	var n_str = '';
	
	for(var i = 0, n = s.length; i < n; i++){
		
		var c = s[i];
		
		if(isuppercase(c) || isspecial(c)){
			
			if (e_str.length > 0){
				
				if(!f[e_str]) f[e_str] = 0;
				f[e_str] += n_str.length > 0 ? parseInt(n_str) : 1;
			}
			
			e_str = isuppercase(c) ? c : '';
			n_str = '';
		}
		else if(islowercase(c)) e_str += c;
		else if(isdigit(c)) n_str += c;
	}
	
	if (e_str.length > 0){
				
		if(!f[e_str]) f[e_str] = 0;
		f[e_str] += n_str.length > 0 ? parseInt(n_str) : 1;
	}
	
	return f;
}

function exact_mass(s){
	
	var f = formula_from_str(s);
	var m = 0;
	
	for(var el in f){
		
		if(!MASS_LIST[el]) return -1;
		m += f[el]*MASS_LIST[el];
	}
	
	return m;
}

function MZ_RANGE(mz, ppm){
	
	return {min:mz*(1 - 1e-6*ppm), max:mz*(1 + 1e-6*ppm)}
}