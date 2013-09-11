<?php
/*
Planning Biblio, Version 1.5.5
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.txt et COPYING.txt
Copyright (C) 2011-2013 - Jérôme Combes

Fichier : statistiques/agents.php
Création : mai 2011
Dernière modification : 10 septembre 2013
Auteur : Jérôme Combes, jerome@planningbilbio.fr

Description :
Affiche les statistiques par agent: nom des postes occupés, nombre d'heures par poste, nombre de samedis travaillés, 
jours feriés travaillés, nombre d'heures d'absences

Page appelée par le fichier index.php, accessible par le menu statistiques / Par agents
*/

require_once "class.statistiques.php";

echo "<h3>Statistiques par agent</h3>\n";

// Initialisation des variables :
$joursParSemaine=$config['Dimanche']?7:6;
$postes=null;
$selected=null;
$heure=null;
$agent_tab=null;

include "include/horaires.php";
//		--------------		Initialisation  des variables 'debut','fin' et 'agents'		-------------------
if(!array_key_exists('stat_agents_agents',$_SESSION)){
  $_SESSION['stat_agents_agents']=null;
}
if(!array_key_exists('stat_debut',$_SESSION)){
  $_SESSION['stat_debut']=null;
  $_SESSION['stat_fin']=null;
}
$debut=isset($_GET['debut'])?$_GET['debut']:$_SESSION['stat_debut'];
$fin=isset($_GET['fin'])?$_GET['fin']:$_SESSION['stat_fin'];
$agents=isset($_GET['agents'])?$_GET['agents']:$_SESSION['stat_agents_agents'];
if(!$debut){
  $debut=date("Y")."-01-01";
}
$_SESSION['stat_debut']=$debut;
if(!$fin){
  $fin=date("Y-m-d");
}
$_SESSION['stat_fin']=$fin;
$_SESSION['stat_agents_agents']=$agents;
$tab=array();

//		--------------		Récupération de la liste des agents pour le menu déroulant		------------------------
$db=new db();
$db->query("SELECT * FROM `{$dbprefix}personnel` WHERE `actif`='Actif' ORDER BY `nom`,`prenom`;");
$agents_list=$db->result;

if(is_array($agents) and $agents[0]){
  //	Recherche du nombre de jours concernés
  $db=new db();
  $db->query("SELECT `date` FROM `{$dbprefix}pl_poste` WHERE `date` BETWEEN '$debut' AND '$fin' GROUP BY `date`;");
  $nbJours=$db->nb;
  $nbSemaines=$nbJours>0?$nbJours/$joursParSemaine:1;

  //	Recherche des infos dans pl_poste et postes pour tous les agents sélectionnés
  //	On stock le tout dans le tableau $resultat
  $agents_select=join($agents,",");
  $db=new db();
  $req="SELECT `{$dbprefix}pl_poste`.`debut` as `debut`, `{$dbprefix}pl_poste`.`fin` as `fin`, 
    `{$dbprefix}pl_poste`.`date` as `date`, `{$dbprefix}pl_poste`.`perso_id` as `perso_id`, 
    `{$dbprefix}pl_poste`.`poste` as `poste`, `{$dbprefix}pl_poste`.`absent` as `absent`, 
    `{$dbprefix}postes`.`nom` as `poste_nom`, `{$dbprefix}postes`.`etage` as `etage` 
    FROM `{$dbprefix}pl_poste` 
    INNER JOIN `{$dbprefix}postes` ON `{$dbprefix}pl_poste`.`poste`=`{$dbprefix}postes`.`id` 
    WHERE `{$dbprefix}pl_poste`.`date`>='$debut' AND `{$dbprefix}pl_poste`.`date`<='$fin' 
    AND `{$dbprefix}pl_poste`.`supprime`<>'1' AND `{$dbprefix}postes`.`statistiques`='1' 
    AND `{$dbprefix}pl_poste`.`perso_id` IN ($agents_select) 
    ORDER BY `poste_nom`,`etage`;";
  $db->query($req);
  $resultat=$db->result;
  
  //	Recherche des infos dans le tableau $resultat (issu de pl_poste et postes)
  //	pour chaques agents sélectionnés
  foreach($agents as $agent){
    if(array_key_exists($agent,$tab)){
      $heures=$tab[$agent][2];
      $total_absences=$tab[$agent][5];
      $samedi=$tab[$agent][3];
      $dimanche=$tab[$agent][6];
      $h19=$tab[$agent][7];
      $h20=$tab[$agent][8];
      $absences=$tab[$agent][4];
      $feries=$tab[$agent][9];
    }
    else{
      $heures=0;
      $total_absences=0;
      $samedi=array();
      $dimanche=array();
      $absences=array();
      $h19=array();
      $h20=array();
      $feries=array();
    }
    $postes=Array();
    if(is_array($resultat)){
      foreach($resultat as $elem){
	if($agent==$elem['perso_id']){
	  if($elem['absent']!="1"){		// on compte les heures et les samedis pour lesquels l'agent n'est pas absent
	    // on créé un tableau par poste avec son nom, étage et la somme des heures faites par agent
	    if(!array_key_exists($elem['poste'],$postes)){
	      $postes[$elem['poste']]=Array($elem['poste'],$elem['poste_nom'],$elem['etage'],0);
	    }
	    $postes[$elem['poste']][3]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $heures+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $d=new datePl($elem['date']);
	    if($d->sam=="samedi"){	// tableau des samedis
	      if(!array_key_exists($elem['date'],$samedi)){ // on stock les dates et la somme des heures faites par date
		$samedi[$elem['date']][0]=$elem['date'];
		$samedi[$elem['date']][1]=0;
	      }
	      $samedi[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }
	    if($d->position==0){		// tableau des dimanches
	      if(!array_key_exists($elem['date'],$dimanche)){ 	// on stock les dates et la somme des heures faites par date
		$dimanche[$elem['date']][0]=$elem['date'];
		$dimanche[$elem['date']][1]=0;
	      }
	      $dimanche[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }
	    if(jour_ferie($elem['date'])){
	      if(!array_key_exists($elem['date'],$feries)){
		$feries[$elem['date']][0]=$elem['date'];
		$feries[$elem['date']][1]=0;
	      }
	      $feries[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    }

	    foreach($agents_list as $elem2){
	      if($elem2['id']==$agent){	// on créé un tableau avec le nom et le prénom de l'agent.
		$agent_tab=array($agent,$elem2['nom'],$elem2['prenom']);
		break;
	      }
	    }
	    //	On compte les 19-20
	    if($elem['debut']=="19:00:00"){
	      $h19[]=$elem['date'];
	    }
	    //	On compte les 20-22
	    if($elem['debut']=="20:00:00"){
	      $h20[]=$elem['date'];
	    }
	  }
	  else{				// On compte les absences
	    if(!array_key_exists($elem['date'],$absences)){
	      $absences[$elem['date']][0]=$elem['date'];
	      $absences[$elem['date']][1]=0;
	    }
	    $absences[$elem['date']][1]+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    $total_absences+=diff_heures($elem['debut'],$elem['fin'],"decimal");
	    // $absences[]=array($elem['date'],diff_heures($elem['debut'],$elem['fin'],"decimal"));
	    
						    // A CONTINUER  
	  }
			    // On met dans tab tous les éléments (infos postes + agents + heures)
	  $tab[$agent]=array($agent_tab,$postes,$heures,$samedi,$absences,$total_absences,$dimanche,$h19,$h20,$feries);
	}
      }
    }
  }
}
// passage en session du tableau pour le fichier export.php
$_SESSION['stat_tab']=$tab;

//		--------------		Affichage en 2 partie : formulaire à gauche, résultat à droite
echo "<table><tr style='vertical-align:top;'><td style='width:300px;'>\n";
//		--------------		Affichage du formulaire permettant de sélectionner les dates et les agents		-------------
echo "<form name='form' action='index.php' method='get'>\n";
echo "<input type='hidden' name='page' value='statistiques/agents.php' />\n";
echo "<table>\n";
echo "<tr><td>Début : </td>\n";
echo "<td><input type='text' name='debut' value='$debut' />&nbsp;<img src='img/calendrier.gif' onclick='calendrier(\"debut\");' alt='calendrier' />\n";
echo "</td></tr>\n";
echo "<tr><td>Fin : </td>\n";
echo "<td><input type='text' name='fin' value='$fin' />&nbsp;<img src='img/calendrier.gif' onclick='calendrier(\"fin\");' alt='calendrier' />\n";
echo "</td></tr>\n";
echo "<tr style='vertical-align:top'><td>Agents : </td>\n";
echo "<td><select name='agents[]' multiple='multiple' size='20' onchange='verif_select(\"agents\");'>\n";
if(is_array($agents_list)){
  echo "<option value='Tous'>Tous</option>\n";
  foreach($agents_list as $elem){
    if($postes){
	    $selected=in_array($elem['id'],$agents)?"selected='selected'":null;
    }
    echo "<option value='{$elem['id']}' $selected>{$elem['nom']} {$elem['prenom']}</option>\n";
  }
}
echo "</select></td></tr>\n";
echo "<tr><td colspan='2' style='text-align:center;'>\n";
echo "<input type='button' value='Effacer' onclick='location.href=\"index.php?page=statistiques/agents.php&amp;debut=&amp;fin=&amp;agents=\"' />\n";
echo "&nbsp;&nbsp;<input type='submit' value='OK' />\n";
echo "</td></tr>\n";
echo "<tr><td colspan='2'><hr/></td></tr>\n";
echo "<tr><td>Exporter </td>\n";
echo "<td><a href='javascript:export_stat(\"agent\",\"csv\");'>CSV</a>&nbsp;&nbsp;\n";
echo "<a href='javascript:export_stat(\"agent\",\"xsl\");'>XLS</a></td></tr>\n";
echo "</table>\n";
echo "</form>\n";

//		--------------------------		2eme partie (2eme colonne)		--------------------------
echo "</td><td>\n";

// 		--------------------------		Affichage du tableau de résultat		--------------------
if($tab){
  echo "<b>Statistiques par agent du ".dateFr($debut)." au ".dateFr($fin)."</b><br/>\n";
  echo $nbJours>1?"$nbJours jours, ":"$nbJours jour, ";
  echo $nbSemaines>1?number_format($nbSemaines,1,',',' ')." semaines":number_format($nbSemaines,1,',',' ')." semaine";
  echo "<table border='1' cellspacing='0' cellpadding='0'>\n";
  echo "<tr class='th'>\n";
  echo "<td style='width:200px; padding-left:8px;'>Agents</td>\n";
  echo "<td style='width:280px; padding-left:8px;'>Postes</td>\n";
  echo "<td style='width:120px; padding-left:8px;'>Samedi</td>\n";
  if($config['Dimanche']){
    echo "<td style='width:120px; padding-left:8px;'>Dimanche</td>\n";
  }
  echo "<td style='width:120px; padding-left:8px;'>J. Feri&eacute;s</td>\n";
  echo "<td style='width:120px; padding-left:8px;'>19-20</td>\n";
  echo "<td style='width:120px; padding-left:8px;'>20-22</td>\n";
  echo "<td style='width:120px; padding-left:8px;'>Absences</td></tr>\n";
  foreach($tab as $elem){
    echo "<tr style='vertical-align:top;'>\n";
    //	Affichage du nom des agents dans la 1ère colonne
    echo "<td style='padding-left:8px;'><b>{$elem[0][1]} {$elem[0][2]}</b><br/>\n";
    echo "Total : ".number_format($elem[2],1,',',' ')." heures<br/>\n";
    $jour=$elem[2]/$nbJours;
    $hebdo=$jour*$joursParSemaine;
    // echo "Moyenne jour. : ".round($jour,2)." heures<br/>\n";
    echo "Moyenne hebdo. : ".number_format(round($hebdo,2),2,',',' ')." heures<br/>\n";
    echo "</td>\n";
    //	Affichage du noms des postes et des heures dans la 2eme colonne
    echo "<td style='padding-left:8px;'>";
    echo "<table>\n";
    foreach($elem[1] as $poste){
      echo "<tr style='vertical-align:top;'><td>\n";
      echo "{$poste[1]} ({$poste[2]})";
      echo "</td><td>\n";
      // $heure=$agent[3]>1?"heures":"heure";
      echo number_format($poste[3],1,',',' ')." $heure";
      echo "</td></tr>\n";
    }
    echo "</table>\n";
    echo "</td>\n";
    //	Affichage du nombre de samedis travaillés et les heures faites par samedi
    echo "<td style='padding-left:8px;'>";
    $samedi=count($elem[3])>1?"samedis":"samedi";
    echo count($elem[3])." $samedi";		//	nombre de samedi
    echo "<br/>\n";
    sort($elem[3]);				//	tri les samedis par dates croissantes
    foreach($elem[3] as $samedi){			//	Affiche les dates et heures des samedis
      echo dateFr($samedi[0]);			//	date
      echo "&nbsp;:&nbsp;".number_format($samedi[1],1,',',' ')."<br/>";	// heures
    }
    echo "</td>\n";
    if($config['Dimanche']){
      echo "<td style='padding-left:8px;'>";
      $dimanche=count($elem[6])>1?"dimanches":"dimanche";
      echo count($elem[6])." $dimanche";	//	nombre de dimanche
      echo "<br/>\n";
      sort($elem[6]);				//	tri les dimanches par dates croissantes
      foreach($elem[6] as $dimanche){		//	Affiche les dates et heures des dimanches
	echo dateFr($dimanche[0]);		//	date
	echo "&nbsp;:&nbsp;".number_format($dimanche[1],1,',',' ')."<br/>";	//	heures
      }
      echo "</td>\n";
    }

    echo "<td style='padding-left:8px;'>";					//	Jours feries
    $ferie=count($elem[9])>1?"J. feri&eacute;s":"J. feri&eacute;";
    echo count($elem[9])." $ferie";		//	nombre de dimanche
    echo "<br/>\n";
    sort($elem[9]);				//	tri les dimanches par dates croissantes
    foreach($elem[9] as $ferie){		// 	Affiche les dates et heures des dimanches
      echo dateFr($ferie[0]);			//	date
      echo "&nbsp;:&nbsp;".number_format($ferie[1],1,',',' ')."<br/>";	//	heures
    }
    echo "</td>";	

    echo "<td>\n";				//	Affichage des 19-20
    if(array_key_exists(0,$elem[7])){
      sort($elem[7]);
      echo "Nb 19-20 : ";
      echo count($elem[7]);
      foreach($elem[7] as $h19){
	echo "<br/>".dateFr($h19);
      }
    }
    echo "</td>\n";
    echo "<td>\n";				//	Affichage des 20-22
    if(array_key_exists(0,$elem[8])){
      sort($elem[8]);
      echo "Nb 20-22 : ";
      echo count($elem[8]);
      foreach($elem[8] as $h20){
	echo "<br/>".dateFr($h20);
      }
    }
    echo "</td>\n";
	    
    echo "<td>\n";
    if($elem[5]){				//	Affichage du total d'heures d'absences
      echo "Total : ".number_format($elem[5],1,',',' ')."<br/>";
    }
    sort($elem[4]);				//	tri les absences par dates croissantes
    foreach($elem[4] as $absences){		//	Affiche les dates et heures des absences
      echo dateFr($absences[0]);		//	date
      echo "&nbsp;:&nbsp;".number_format($absences[1],1,',',' ')."<br/>";	// heures
      // echo "&nbsp;:&nbsp;".$absences[1]."<br/>";	// heures
    }
    echo "</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}
//		----------------------			Fin d'affichage		----------------------------
echo "</td></tr></table>\n";
?>