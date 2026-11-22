function scanGroup(state, chars, type, points, flag) {
  for (const symbol of chars) {
    if (state.pass.includes(symbol)) {
      state.score += points;
      state.flags[flag] = true;
      state.membership[state.pass.indexOf(symbol)] = type;
    }
  }
}

function noisyTransitions(membership) {
  var lastType = { value: '' };
  var bonus = 0;
  for (var index in membership) {
    if (lastType.value !== membership[index]) {
      bonus += .25;
      lastType.value = membership[index];
    }
  }
  return bonus;
}

function checkQuality(pass) {
  var state = { pass, score: 0, membership: [], flags: { hasPunctuation: false, hasUppercase: false, hasLowercase: false, hasNumber: false } };
var punctuations = '!@#$%^&*()~`_-+={}[];:"<>,./?\\\''.split('');
var uppercases = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
var lowercases = 'abcdefghijklmnopqrstuvwxyz'.split('');
  scanGroup(state, punctuations, 'P', 1, 'hasPunctuation');
  scanGroup(state, uppercases, 'U', .5, 'hasUppercase');
  scanGroup(state, lowercases, 'L', .5, 'hasLowercase');
  scanGroup(state, '0123456789', 'N', .5, 'hasNumber');
  state.score += noisyTransitions(state.membership);
  let { score, flags } = state;
  const levels=[[8,'Ottima'],[7,'Molto Buona'],[6,'Buona'],[5,'Discreta'],[4,'Decente']];
  if (flags.hasPunctuation) {score++;}
  if (flags.hasNumber) {score++;}
  if (flags.hasUppercase) {score++;}
  if (flags.hasLowercase) {score++;}
  for(const [minimum,label] of levels){
    if(score>minimum){return label+' ('+score+')';}
  }
  return 'Inutile ('+score+')';
}
