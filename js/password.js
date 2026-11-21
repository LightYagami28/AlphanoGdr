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
	for (const punctuation of punctuations) {
		if (pass.includes(punctuation)) {
			score++;
			hasPunctuation = true;
			membership[pass.indexOf(punctuation)] = 'P';
		}
	}
	for (const uppercase of uppercases) {
		if (pass.includes(uppercase)) {
			score += .5;
			hasUppercase = true;
			membership[pass.indexOf(uppercase)] = 'U';
		}
	}
	for (const lowercase of lowercases) {
		if (pass.includes(lowercase)) {
			score += .5;
			hasLowercase = true;
			membership[pass.indexOf(lowercase)] = 'L';
		}
	}
	for (const digit of '0123456789') {
		if (pass.includes(digit)) {
			score += .5;
			hasNumber = true;
			membership[pass.indexOf(digit)] = 'N';
		}
	}
	// calculate the "noisyness" of the password
	// each transition from one group of symbols to the next adds .25 to the score
	// NoI$yNeSs of that string is 4.25
	// noisyness of that string is .25
	var lastType = '';
	for (var index in membership) {
		if (lastType !== membership[index]) {
			score += .25;
			lastType = membership[index];
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