<?php

namespace Comppi\ProteinSearchBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class ProteinSearchController extends Controller
{
    // @TODO: implement a central input processing bundle/service + Symfony-style forms
	private $species_requested = array(
		'Hs' => 1,
		'Dm' => 1,
		'Ce' => 1,
		'Sc' => 1
	);
	
	public function proteinSearchAction($protein_name = '')
    {
        $T = array(
            'ls' => array(),
			'keyword' => '',
			'protein_names' => '',
        );
		
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
			$DB = $this->get('database_connection');
			
			$this->species_requested['Hs'] = intval($request->request->get('fProtSearchSpecHs'));
			$this->species_requested['Dm'] = intval($request->request->get('fProtSearchSpecDm'));
			$this->species_requested['Ce'] = intval($request->request->get('fProtSearchSpecCe'));
			$this->species_requested['Sc'] = intval($request->request->get('fProtSearchSpecSc'));
			
			$keywords = array();
			if ($request->request->get('fProtSearchKeyword')) {
				$keywords[] = mysql_real_escape_string($request->request->get('fProtSearchKeyword'));
				$T['keyword']  = $request->request->get('fProtSearchKeyword');
			}
			if ($request->request->get('fProtSearchMultiNames')) {
				$T['protein_names']  = $request->request->get('fProtSearchMultiNames');
				$keywords = array_merge(explode("\n", $request->request->get('fProtSearchMultiNames')), $keywords);
			}

			if (!empty($keywords)) {
				$name_cond = array();
				foreach($keywords as $name) {
					$name = str_replace("\r", '', $name); // different carriage returns on different platforms...
					$name_cond[] = "(p1.proteinName LIKE '%".mysql_real_escape_string($name)."%' OR p2.proteinName LIKE '%".mysql_real_escape_string($name)."%')";
					$pg_cond[] = "(p.proteinName LIKE '%".mysql_real_escape_string($name)."%')";
				}

				// Low-level Doctrine DBAL commands with custom query building to have better control
				// @TODO: convert to Doctrine query builder ( conn->createQueryBuilder() )?
				$locs = $this->get('comppi.build.localizationTranslator');
				$one_sp_at_least = false;

				foreach($this->species_requested as $sp => $specie_needed) {
					$one_sp_at_least = true;
					if ( $specie_needed ) {
						$sql_pg = "SELECT DISTINCT COUNT(i.actorAId) AS rownum FROM Interaction$sp i LEFT JOIN Protein$sp p ON i.actorAId=p.id WHERE ".join(' OR ', $pg_cond);
						$r_pg = $DB->query( $sql_pg );
						$r_rownum = $r_pg->fetch();
						$max_rownum = (int)$r_rownum['rownum'];
						// @TODO: to be continued...
						
						$sql = "SELECT DISTINCT p1.proteinName AS protA, p2.proteinName AS protB, i.actorAId, i.actorBId, ptl1.localizationId AS locAId, ptl1.pubmedId AS locASrc, ptl2.localizationId AS locBId, ptl1.pubmedId AS locBSrc FROM Interaction$sp i LEFT JOIN Protein$sp p1 ON i.actorAId=p1.id LEFT JOIN Protein$sp p2 ON i.actorBId=p2.id LEFT JOIN ProteinToLocalization$sp ptl1 ON actorAId=ptl1.proteinId LEFT JOIN ProteinToLocalization$sp ptl2 ON actorBId=ptl2.proteinId WHERE ".join(' OR ', $name_cond).' LIMIT 20';

						$results = $DB->query( $sql );
						// @TODO: exception handling here
						while ( $p = $results->fetch() ) {
							$T['ls'][] = array(
								'protA' => $p['protA'],
								'locA' => (empty($p['locAId']) ? 'N/A' : $locs->getHumanReadableLocalizationById($p['locAId'])),
								'locASrcUrl' => (empty($p['locAId']) ? '' : $this->linkToPubmed($p['locASrc'])),
								'protB' => $p['protB'],
								'locB' => (empty($p['locBId']) ? 'N/A' : $locs->getHumanReadableLocalizationById($p['locBId'])),
								'locBSrcUrl' => (empty($p['locBId']) ? '' : $this->linkToPubmed($p['locBSrc']))
							);
						}
					}
				}
				//die( var_dump($T['ls']) );
				if ( !$one_sp_at_least ) {
					$this->get('session')->setFlash('notice', 'Please select at least one genus!');
				}
			} else {
				// @TODO: set up a symfony-style proper form validation
				$this->get('session')->setFlash('notice', 'Please fill in at least one protein name!');
			}
		}
		
		$T['need_hs'] = $this->species_requested['Hs'];
		$T['need_dm'] = $this->species_requested['Dm'];
		$T['need_ce'] = $this->species_requested['Ce'];
		$T['need_sc'] = $this->species_requested['Sc'];
		
		return $this->render('ComppiProteinSearchBundle:ProteinSearch:index.html.twig', $T);
	}
	
	private function linkToPubmed($pubmed_uid)
	{
		return 'http://www.ncbi.nlm.nih.gov/pubmed/'.$pubmed_uid;
	}
}
