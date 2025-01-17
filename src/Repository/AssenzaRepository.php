<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Repository;

use App\Entity\Alunno;


/**
 * Assenza - repository
 */
class AssenzaRepository extends BaseRepository {

  /**
   * Elimina le assenze dell'alunno nel periodo indicato
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   * @param \DateTime $inizio Data di inizio
   * @param \DateTime $fine Data di fine
   */
  public function elimina(Alunno $alunno, \DateTime $inizio, \DateTime $fine) {
    // crea query base
    $this->createQueryBuilder('ass')
      ->delete()
      ->where('ass.alunno=:alunno AND ass.data BETWEEN :inizio AND :fine')
      ->setParameters(['alunno' => $alunno, 'inizio' => $inizio->format('Y-m-d'), 'fine' => $fine->format('Y-m-d')])
      ->getQuery()
      ->execute();
  }

  /**
   * Restituisce il numero di assenze ingiustificate
   *
   * @param Alunno $alunno Alunno di cui si vogliono eliminare le assenze
   */
  public function assenzeIngiustificate(Alunno $alunno) {
    // crea query base
    $assenze = $this->createQueryBuilder('ass')
      ->select('COUNT(ass.id)')
      ->where('ass.alunno=:alunno AND ass.giustificato IS NULL')
      ->setParameters(['alunno' => $alunno])
      ->getQuery()
      ->getSingleScalarResult();
    return $assenze;
  }

}
