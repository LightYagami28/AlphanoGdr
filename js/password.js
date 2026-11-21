function checkQuality(pass) {
	var q = new String("");
	var score = 0;
	var punctuations = '!@#$%^&*()~`_-+={}[];:"<>,./?\\\''.split('');
	var uppercases = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
	var lowercases = 'abcdefghijklmnopqrstuvwxyz'.split('');
	var hasPunctuation = false;
	var hasUppercase = false;
	var hasLowercase = false;
	var hasNumber = false;
	var membership = [];
	for (const ci in punctuations) {
		if (pass.indexOf(punctuations[ci]) != -1) {
			score++;
			hasPunctuation = true;
			membership[pass.indexOf(punctuations[ci])] = 'P';
		}
	}
	for (const cu in uppercases) {
		if (pass.indexOf(uppercases[cu]) != -1) {
			score += .5;
			hasUppercase = true;
			membership[pass.indexOf(uppercases[cu])] = 'U';
		}
	}
	for (const cl in lowercases) {
		if (pass.indexOf(lowercases[cl]) != -1) {
			score += .5;
			hasLowercase = true;
			membership[pass.indexOf(lowercases[cl])] = 'L';
		}
	}
	for (let cn=0;cn<=9;cn++) {
		if (pass.indexOf(cn) != -1) {
			score += .5;
			hasNumber = true;
			membership[pass.indexOf(cn)] = 'N';
		}
	}
	// calculate the "noisyness" of the password
	// each transition from one group of symbols to the next adds .25 to the score
	// NoI$yNeSs of that string is 4.25
	// noisyness of that string is .25
	var lastType = '';
	for (const i in membership) {
		if (lastType != membership[i]) {
			score += .25;
			lastType = membership[i];
		}
	}
	if (hasPunctuation) score++;
	if (hasNumber) score++;
	if (hasUppercase) score++;
	if (hasLowercase) score++;
	
	if (score > 8) q = 'Ottima';
	else if (score > 7) q = 'Molto Buona';
	else if (score > 6) q = 'Buona';
	else if (score > 5) q = 'Discreta';
	else if (score > 4) q = 'Decente';
	else  q = 'Inutile';
	return (q + ' (' + score + ')');
}