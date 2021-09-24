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
		
		# ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ Vi testar att skicka mötesagendan
		# Kolla om dagens datum  = mötesdatum
		$ReminderDate = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));			
		print("<p>".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"]." TEST Idag");
		 
		if ($rad["Typ"] == 1)
		{				
			$MeetID         =  $rad["MeetId"];
			$mailSubject 	= "Dagens mötesagenda: ".$rad["Rubrik"]." ".$rad["Datum"]." kl ".$rad["Start"];				
			$mailTo 		= "lll@llldata.se"; 
						
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
	print("<br>".$MeetID."|".$RubrikID."|".$svar);
	return $svar;
}			
?>
				
</html>
