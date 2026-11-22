var righe = new Array();
var locations = new Object();
var araldiche = new Array();
var oldtree = "chat";
var umori = new Array();
umori[0] = new Object();
umori[0]["id"] = 0;
umori[0]["nome"] = "";
function ShowChat()
{
	if(righe.length) while(righe.length)
	{
		const riga = righe.pop();
		if(locations["" + owner + ""] && riga["pg"]!=owner && locations["" + riga["pg"] + ""] && locations["" + owner + ""].length && Compara(locations["" + owner + ""],locations["" + riga["pg"] + ""])) document.getElementById("chatbox").innerHTML = "<DIV class=\"riga same-location\">" + riga["testo"] + "</DIV>" + document.getElementById("chatbox").innerHTML;
		else document.getElementById("chatbox").innerHTML = "<DIV class=\"riga\">" + riga["testo"] + "</DIV>" + document.getElementById("chatbox").innerHTML;
	}
}
function RefreshChat()
{
	try { parent.control.RefreshIt(); }
	catch(e) { }
}
function switch_frase()
{
	document.send.frase.value = document.send.comodo.value;
	document.send.comodo.value = "";
}
function recupera_frase()
{
	document.send.comodo.value = document.send.frase.value;
}
function CaricaUmori()
{
	let out = '<UL id="umori">';
	for(var i = 0; i < umori.length; i++)
	{
		out += "<LI><A href=\"javascript:ImpostaUmore(" + i + ")\">";
		if(i) out += "<IMG src=\"/chatimg/umori/" + umori[i]["id"] + ".gif\" border=\"0\"> ";
		out += umori[i]["nome"] + "</A></LI>";
	}
	out += "</UL>";
	MostraDescrizione("menu",out);
}
function ImpostaUmore(id)
{
	document.send.umore.value = umori[id]["id"];
	let out = '<A href="javascript:CaricaUmori()">Umore: ';
	if(id) out += "<IMG src=\"/chatimg/umori/" + umori[id]["id"] + ".gif\" border=\"0\"> ";
	document.getElementById("umore").innerHTML = out + umori[id]["nome"] + "</A>";
	NascondiDescrizione("menu");
}
function ImpostaTastiRegistrazione(hasrecord)
{
	if(hasrecord)
	{
		out = "<IMG src=\"/assets/placeholder.svg\" alt=\"Avvia Registrazione Giocata\" title=\"Avvia Registrazione Giocata\" border=\"0\"><A href=\"javascript:EventoRegistrazione('salva')\"><IMG src=\"/assets/placeholder.svg\" alt=\"Salva Registrazione Giocata\" title=\"Salva Registrazione Giocata\" border=\"0\"></A>";
	}
	else
	{
		out = "<A href=\"javascript:EventoRegistrazione('registra')\"><IMG src=\"/assets/placeholder.svg\" alt=\"Avvia Registrazione Giocata\" title=\"Avvia Registrazione Giocata\" border=\"0\"></A><IMG src=\"/assets/placeholder.svg\" alt=\"Salva Registrazione Giocata\" title=\"Salva Registrazione Giocata\" border=\"0\">";
	}
	document.getElementById("registrazione").innerHTML = out;
}
function EventoRegistrazione(evento)
{
	var ok = 1;
	while(ok)
	{
		try
		{
			parent.control.RefreshIt(evento);
			ok = 0;
		}
		catch(e) {console.error('Aggiornamento chat non disponibile',e);}
	}
}