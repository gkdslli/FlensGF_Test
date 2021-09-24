<?php 
session_start(); 
include_once("fgfMainInclude.php");
include_once("avgiftFunkSkapaKvittoMedlKortMail.php");

$sida   = substr($_SERVER["PHP_SELF"],1);

// ------------  Registrera besöksstatistik för visad sida
		isrt_VisitedPage();
?> 

<HTML>
<head>
<link type="text/css" rel="stylesheet" href="fgfScreen.css" media="screen" />
<link type="text/css" rel="stylesheet" href="fgfPrint.css" media="print" />


<style>

div.agenda {
  font-size: large;
}
</style>
</head>

<body> 

<?php 
	

#**************************************************************************************
#**
#**                      Kontrollera i Kalendertabellen om det är något mail som skall skickas
#**
#**************************************************************************************


$ToDay = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));
#Styrelsemöte();
MötesKoll();



function Kalender() 
{		
global $db;
	$sql = "SELECT count(*) FROM `ADM_kalender` WHERE `MailDatum` < '".$ToDay."' AND `MailDatum` <> '0000-00-00'";		
	$result = mysqli_query($db,$sql) or die (mysqli_error());

	list($Antal) = mysqli_fetch_row($result);  
	print("Antal aktuella händelser = ". $Antal);

	if ($Antal > 0)
	{
		$hamta = "SELECT * FROM `kalender` WHERE `MailDatum` < '".$ToDay."' AND `MailDatum` <> '0000-00-00'";	
		#print("$hamta");	
		$resultat = mysqli_query($db,$hamta) or die("Det gick inte att hämta information från databasen!");
		while($rad = mysqli_fetch_array($resultat))
		{
			print("<hr>");
			$nextDate = NextYear($rad["MailDatum"]);
			#Syntax: mail(to,subject,message,headers,parameters)
			$mailTo 		= $rad["MailMottagare"]; 
			$mailSubject 	= $rad["Rubrik"];
			
			$mailMessage	="<html><head><title>HTML email</title></head><body>".
			"<h2>".$rad["Rubrik"]."</h2>".$rad["Text"];
			
			if ($rad["Ansvarig"] != '') $mailMessage .= "<p>Ansvar: ".$rad["Ansvarig"];
			
			$mailMessage .=	"<br>".$rad["Ovrigt"].		
			"<p><small><font color=blue>".
			"Det här mailet är automatiskt genererat från vår kalender. Svara därför inte på det.".
			"<p>Mailmottagare är ".$rad["MailMottagare"].
			"<br>Detta meddelande kommer att skickas ".$nextDate." nästa gång.".
			"</font></small>".
			"</body></html>";
			// Always set content-type when sending HTML email
			$mailHeader = "MIME-Version: 1.0" . "\r\n";
			$mailHeader .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
			$mailHeader .= 'From: Kalenderfunktionen <Kalender@FlensGF.se>' . "\r\n";
					
			mail($mailTo, $mailSubject ,$mailMessage, $mailHeader ); 
			print($mailTo. $mailSubject .$mailMessage. $mailHeader ); 
		}
		mysqli_free_result($resultat);
					
					
	}		
}

function Styrelsemöte() 
{
global $db;
	#Hämta datum för nästa styrelsemöte. Skicka påminnelse till styrelsen@flensgf.se om mötet är om x dagar
	$xDagar = 3;

	$ToDay = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));				
	$query = mysqli_query($db,"SELECT `Datum`,`Start`,`Plats`,`Info` 
				FROM `INTERN_Meeting` 
				WHERE `Typ` = 1 and `Datum` in 
				(select min(`Datum`) from `t_Meeting` 
				WHERE `Datum` > '$ToDay') ");
	$Meeting = mysqli_fetch_array($query);
	
	# Sätt MeetDate till dagens datum plus $xDagar
	$MeetDate = date('Y-m-d',mktime(0,0,0,date('m'),date('d')+ $xDagar,date('Y')));
	
	#Skicka mail om möstesdatum och MeetDate är samma
	if ($Meeting["Datum"] == $MeetDate)
	{

	
		$mailSubject 	= "Påminnelse: Styrelsemöte ".$Meeting["Datum"]." kl ".$Meeting["Start"];				
		$mailTo 		= "styrelsen@flensgf.se"; 				

		#$mailTo 		= "lll@llldata.se"; 
		$mailMessage  = "<html><head><title>HTML email</title></head><body>".
		$mailMessage .= "<p><b>Hej!</b><br>Här kommer en liten automatisk påminnelse från Gymnastikföreningens webbhotell:<p> ".
							"<h2>Styrelsemöte ".$Meeting["Datum"]." kl ".$Meeting["Start"]."</h2>".

							" Inträffar om " . $xDagar . " dagar.<p> Plats: <b>".$Meeting["Plats"]."</b>";
		$mailMessage .=	"<br>".$Meeting["Info"].
					"<p><small><font color=blue>".
					"Det här mailet är automatiskt genererat från vår kalender. Svara därför inte på det.".
					"</font></small>".
					"</body></html>";
		// Always set content-type when sending HTML email
		$mailHeader = "MIME-Version: 1.0" . "\r\n";
		$mailHeader .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
		$mailHeader .= 'From: FlensGF <automat@FlensGF.se>' . "\r\n";
				
		mail($mailTo, $mailSubject ,$mailMessage, $mailHeader ); 
	}
}
	
function MötesKoll() 
{
global $db;
	#Hämta alla möten med datum idag eller högre

	$ToDay = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));				

	$hamta =  "SELECT `MeetId`
						, `Datum`
						, `Start`
						, `Plats`
						, `Info`
						, `Typ`
						, `ReminderID`
						, `Mottagare`
						, `DagarInnan`
						, `Rubrik`
						, `Text` 
				FROM `INTERN_Meeting` a 
				left outer join INTERN_MailReminder b on a.`Typ`= b.ReminderID
				WHERE `Datum` >= '$ToDay' ";
	print($hamta);
					
	$resultat = mysqli_query($db,$hamta) or die("Det gick inte att hämta information från databasen!");
	while($rad = mysqli_fetch_array($resultat))
	{	
		# Kolla om dagens datum plus antal påminnelsedagar = mötesdatum
		$ReminderDate = date('Y-m-d',mktime(0,0,0,date('m'),date('d') + $rad["DagarInnan"],date('Y')));			
		print("<p>".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"]." Påminnelse: ".$ReminderDate." Dagarinnan: ".$rad["DagarInnan"]);
		 
		if ($rad["Datum"] == $ReminderDate)
		{				
			$mailSubject 	= "Påminnelse: ".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"];				
			$mailTo 		= $rad["Mottagare"]; 
			#$mailTo 		= "lll@llldata.se"; 
			
			$mailMessage  = "<html><head><title>HTML email</title></head><body>";
			$mailMessage .= "<p><b>Hej!</b><br><font color=blue>".
							"Här kommer en liten automagisk påminnelse från Flens Gymnastikförening:</font><p> ".
							"<big>".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"]."</big>".
								"<br> Inträffar om " . $rad["DagarInnan"] . " dagar.<p> Plats: <b>".$rad["Plats"]."</b>";
			$mailMessage .=	"<br>".$rad["Info"].
						"<br>".$rad["Text"].
						"<hr>".
						"<table border=1><tr><td>".
						"<p><small><font color=blue>".
						"Det här mailet är automatiskt genererat från hemsidan, så det lönar sig inte att svara på det. ".
						"Hemsidan läser inte mail.".
						"</font></small>".
						"</td></tr></table>".
						"</body></html>";
						

			 $mailHeader = "From: Gymmix Flen <GymMix@FlensGF.se>\r\n". 
					   "MIME-Version: 1.0" . "\r\n" . 
					   "Content-type: text/html; charset=UTF-8" . "\r\n"; 

			// Mail it
					
			#ail($mailTo, $mailSubject ,$mailMessage, $mailHeader ); 	
			mail($mailTo, '=?UTF-8?B?'.base64_encode($mailSubject).'?=',$mailMessage, $mailHeader ); 	
		}
		
		# ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ Vi testar att skicka mötesagendan
		# Kolla om dagens datum  = mötesdatum
		$ReminderDate = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));			
		print("<p>".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"]." TEST Idag");
		 
		if ($rad["Datum"] == $ReminderDate and $rad["Typ"] == 1)
		{				
			$MeetID         =  $rad["MeetId"];
			$mailSubject 	= "Dagens mötesagenda: ".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"];				
			$mailTo 		= $rad["Mottagare"]; 
						
			$mailMessage  = "<html><head>" ;
			$mailMessage .= "<style> div.agenda {";
			$mailMessage .= "font-size: large;}";
			$mailMessage .= "</style><div class='agenda'>";
			$mailMessage .= "</head><body>";
			$mailMessage .= "<p><b>Agenda för dagens möte.</b><br><font color=blue>".
							"Se till att den tillgänglig vid mötet.</font><p> ";
											
	
			#Hämta standard-dagordning
			$typ = 1;
			$AgendaSQL = "SELECT * FROM `INTERN_AgendaRubrik` 
						WHERE `MeetTyp` = $typ
						order by `HuvudGrupp`,`RubrikNr`";
						
			$AgendaResultat = mysqli_query($db,$AgendaSQL) or die(mysqli_error());
			$mailMessage .="<ol>\n";
			$mailMessage .="<li>\n";
			$wHG = 0;
			$underlista = "<ol>\n";
			while ($AgendaRad = mysqli_fetch_array($AgendaResultat))
			{
				if ($AgendaRad['RubrikNr'] == '')
				{
					if ($underlista == '') $mailMessage .="</ol>\n";	#Avsluta underlistan
					if (($AgendaRad["HuvudGrupp"] != $wHG)  and ($wHG > 0))
					{
						$mailMessage .="</li><li>\n";
					}
					$mailMessage .="<b>".$AgendaRad["Rubrik"]."</b>\n";
					$mailMessage .=(getArende($MeetID,$AgendaRad["RubrikID"]));
					$wHG = $AgendaRad["HuvudGrupp"];
					$underlista = "<ol style='list-style-type:lower-alpha'>";
				}
				else
				{
					$tvingande = finnsArende($MeetID,$AgendaRad["HuvudGrupp"]);
					if ( ($tvingande > 0) or ($AgendaRad["Alltid"] == 'J' ) or ($AgendaRad["Alltid"] != 'J' ) )
					{
						$mailMessage .=$underlista;
						$underlista = '';
						$fnt = "";
						$efnt = "";
						if ($AgendaRad["Alltid"] == 'J' ) 
						{
							$fnt = "<font color=black><b>";
							$efnt = "</b></font>";
						}
						$mailMessage .= $fnt."<li>".$AgendaRad["Rubrik"].$efnt;
						$mailMessage .= getArende($MeetID,$AgendaRad["RubrikID"]);
						$mailMessage .="</li>\n";
					}
				}
			}
			
			mysqli_free_result($AgendaResultat);
	
			$mailMessage .="</ol>\n";
			$mailMessage .="</li>\n";
			$mailMessage .="</ol>\n";

			$mailMessage .=	"<br>".$rad["Info"].
						"<br>".$rad["Text"].
						"<hr>".
						"<table border=1><tr><td>".
						"<p><small><font color=blue>".
						"Det här mailet är automatiskt genererat från hemsidan, så det lönar sig inte att svara på det. ".
						"Hemsidan läser inte mail.".
						"</font></small>".
						"</td></tr></table>".
						"</div>".
						"</body></html>";
						

			 $mailHeader = "From: Gymmix Flen <GymMix@FlensGF.se>\r\n". 
					   "MIME-Version: 1.0" . "\r\n" . 
					   "Content-type: text/html; charset=UTF-8" . "\r\n"; 

			// Mail it
					
			#ail($mailTo, $mailSubject ,$mailMessage, $mailHeader ); 	
			mail($mailTo, '=?UTF-8?B?'.base64_encode($mailSubject).'?=',$mailMessage, $mailHeader ); 	
		}
	}
	mysqli_free_result($resultat);
				
	
}
				
				
				
function NextYear($Datum)  
{
		$YYYY = substr($Datum,0,4); 
		$MM = substr($Datum,5,2);   
		$DD = substr($Datum,8,2);
		$Datum = date('Y-m-d',mktime(0,0,0,$MM,$DD,$YYYY+1));
				
		return $Datum;
}
function finnsArende($MeetID,$HuvudRub) {
	global $db;
	$query = mysqli_query($db,"SELECT count(*) FROM INTERN_AgendaPunkt 
						WHERE MeetID = '$MeetID'  
						AND fkRubrikID in
							(Select RubrikID from  INTERN_AgendaRubrik WHERE HuvudGrupp = '$HuvudRub' )	");
	$Antal = mysqli_fetch_array($query);
	return $Antal[0];
}	
	
function getArende($MeetID,$RubrikID) {
	global $db;
	$Arende = "SELECT Namn, Arende FROM 	INTERN_AgendaPunkt 
						WHERE MeetID = '$MeetID'  
						AND fkRubrikID  = '$RubrikID' 	";
	$svar = '';
	$resultat = mysqli_query($db,$Arende) or die(mysqli_error());
	while ($reslt = mysqli_fetch_array($resultat))
	{
		$svar = $svar . "<br><small><font color=brown>". $reslt["Namn"].":</font></small><font color=red><b> ".$reslt["Arende"] . "</b></font>";
	}
	return $svar;
}			
?>
				
</html>
