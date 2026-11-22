function CreateGMLHelper(obj)
{
	const tools=[
		['Titolo','GMLT'],
		['Paragrafo','GMLP'],
		['Grassetto','GMLB'],
		['Corsivo','GMLC'],
		['Centrato','GMLCE'],
		['Colore del Testo','GMLTC'],
		['Immagine','GMLI'],
		['Collegamento','GMLLNK'],
		['Colore di Sfondo','GMLBGC'],
		['Immagine di Sfondo','GMLBGI'],
		['Tabella','GMLTBL'],
		['Riga di Tabella','GMLRG'],
		['Colonna di Tabella','GMLCL'],
		['Citazione','GMLQ'],
		['Scegli Colore','ApriScegliColore'],
		['Anteprima','ApriAnteprima']
	];
	const bar=document.createElement('span');
	bar.className='gml-toolbar';
	for(const [title,fn] of tools)
	{
		const link=document.createElement('a');
		link.title=title;
		link.href='javascript:'+fn+'('+(fn==='ApriScegliColore'?'':obj)+')';
		const img=document.createElement('img');
		img.src='/assets/placeholder.svg';
		img.setAttribute('border','0');
		img.alt=title;
		link.appendChild(img);
		bar.appendChild(link);
	}
	const anchor=document.currentScript;
	if(anchor){anchor.before(bar);}else {document.body.appendChild(bar);}
}

function ApriScegliColore()
{
	window.open("/vari/colori.html","GColorChooser","toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=300,height=100");
}

function ApriAnteprima(obj)
{
	window.open("","GAnteprimaGML","toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,width=800,height=600");
	var objform = document.createElement("FORM");
	objform.id = "frmAnteprimaGML";
	objform.method = "POST";
	objform.action = "/vari/anteprimagml.php";
	objform.target = "GAnteprimaGML";
	document.body.appendChild(objform);
	//textarea e non input altrimenti alcuni browser non sono contenti e non so perche'
	var objtext = document.createElement("TEXTAREA");
	objtext.name = "testo";
	objtext.value = obj.value;
	objform.appendChild(objtext);
	objform.submit();
	objform.remove();
}

function GMLT(obj)
{
	var tmp = prompt("Inserisci il Titolo","");
	if(tmp.length) Inserisci(obj,"[t]" + tmp + "[/t]");
}

function GMLP(obj)
{
	Inserisci(obj,"[p][/p]");
}

function GMLB(obj)
{
	Inserisci(obj,"[b][/b]");
}

function GMLC(obj)
{
	Inserisci(obj,"[c][/c]");
}

function GMLCE(obj)
{
	Inserisci(obj,"[ce][/ce]");
}

function GMLTC(obj)
{
	var tmp = prompt("Inserisci il colore del testo","");
	if(tmp.length) Inserisci(obj,"[tc " + tmp + "][/tc]");
}

function GMLI(obj)
{
	var tmp = prompt("Inserisci l'URL dell'immagine","");
	if(tmp.length)
	{
		var tmp2 = prompt("Inserisci altezza e larghezza dell'immagine divisi da una x (lascia vuoto se non le vuoi specificare)","");
		if(tmp2.length) Inserisci(obj,"[i " + tmp2 + "]" + tmp + "[/i]");
		else Inserisci(obj,"[i]" + tmp + "[/i]");
	}
}

function GMLLNK(obj)
{
	var tmp = prompt("Inserisci l'URL della pagina da collegare","");
	if(tmp.length)
	{
		var tmp2 = prompt("Inserisci il testo del collegamento (lascia vuoto se vuoi usare l'URL)","");
		if(tmp2.length) Inserisci(obj,"[lnk " + tmp + "]" + tmp2 + "[/lnk]");
		else Inserisci(obj,"[lnk]" + tmp + "[/lnk]");
	}
}

function GMLBGC(obj)
{
	var tmp = prompt("Inserisci il colore di sfondo","");
	if(tmp.length) Inserisci(obj,"[bgc " + tmp + "][/bgc]");
}

function GMLBGI(obj)
{
	var tmp = prompt("Inserisci l'URL dell'immagine di sfondo","");
	if(tmp.length) Inserisci(obj,"[bgi " + tmp + "][/bgi]");
}

function GMLTBL(obj)
{
	var tmp = prompt("Inserisci altezza e larghezza della tabella divisi da una x (lascia vuoto se non le vuoi specificare)","");
	if(tmp.length) Inserisci(obj,"[tbl " + tmp + "][/tbl]");
	else Inserisci(obj,"[tbl][/tbl]");
}

function GMLRG(obj)
{
	Inserisci(obj,"[rg][/rg]");
}

function GMLCL(obj)
{
	var tmp = prompt("Inserisci il numero di colonne da occupare in larghezza (lascia vuoto se non lo vuoi specificare)","");
	if(tmp.length) Inserisci(obj,"[cl " + tmp + "][/cl]");
	else Inserisci(obj,"[cl][/cl]");
}

function GMLQ(obj)
{
	var tmp = prompt("Inserisci il nome del Personaggio che vuoi citare","");
	if(tmp.length) Inserisci(obj,"[q " + tmp + "][/q]");
}

function Inserisci(obj,txt)
{
	const tmp = obj.scrollTop;
	if(document.selection)
	{
		obj.focus();
		const sel = document.selection.createRange();
		sel.text = txt;
	}
	else if(obj.selectionStart || obj.selectionStart == "0") obj.value = obj.value.substring(0, obj.selectionStart) + txt + obj.value.substring(obj.selectionEnd, obj.value.length);
	obj.focus();
	obj.scrollTop = tmp;
}
