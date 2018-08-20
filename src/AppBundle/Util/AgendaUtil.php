<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Util\BachecaUtil;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Docente;
use AppBundle\Entity\Annotazione;
use AppBundle\Entity\Avviso;


/**
 * AgendaUtil - classe di utilità per le funzioni di gestione dell'agenda
 */
class AgendaUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var RouterInterface $router Gestore delle URL
   */
  private $router;

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var TranslatorInterface $trans Gestore delle traduzioni
   */
  private $trans;

  /**
   * @var BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  private $bac;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param RouterInterface $router Gestore delle URL
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param BachecaUtil $bac Classe di utilità per le funzioni di gestione della bacheca
   */
  public function __construct(RouterInterface $router, EntityManagerInterface $em, TranslatorInterface $trans,
                               BachecaUtil $bac) {
    $this->router = $router;
    $this->em = $em;
    $this->trans = $trans;
    $this->bac = $bac;
  }

  /**
   * Recupera i dati degli eventi per il docente indicato relativamente al mese indicato
   *
   * @param Docente $docente Docente a cui sono indirizzati gli eventi
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventi(Docente $docente, $mese) {
    $dati = null;
    // colloqui
    $colloqui = $this->em->getRepository('AppBundle:RichiestaColloquio')->createQueryBuilder('rc')
      ->join('rc.colloquio', 'c')
      ->where('rc.stato=:stato AND MONTH(rc.data)=:mese AND c.docente=:docente')
      ->orderBy('rc.data', 'ASC')
      ->setParameters(['stato' => 'C', 'docente' => $docente, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getData()->format('j'))]['colloqui'] = 1;
    }
    // attivita
    $attivita = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->join('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.classe=cl.id')
      ->where('a.destinatariDocenti=:destinatario AND a.tipo=:tipo AND MONTH(a.data)=:mese AND c.docente=:docente AND c.attiva=:attiva')
      ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'mese' => $mese->format('n'),
        'docente' => $docente, 'attiva' => 1])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->where('a.docente=:docente AND a.tipo=:tipo AND MONTH(a.data)=:mese')
      ->setParameters(['docente' => $docente, 'tipo' => 'V', 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameters(['tipo' => 'F', 'mese' => $mese->format('n')])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[intval($f->getData()->format('j'))]['festivo'] = 1;
    }
    // azione add
    if ($this->azioneEvento('add', new \DateTime(), $docente, null)) {
      // pulsante add
      $dati['azioni']['add'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dettagli degli eventi per il docente indicato relativamente alla data indicata
   *
   * @param Docente $docente Docente a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEvento(Docente $docente, $data, $tipo) {
    $dati = null;
    if ($tipo == 'C') {
      // colloqui
      $dati['colloqui'] = $this->em->getRepository('AppBundle:RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.id,rc.messaggio,c.giorno,so.inizio,so.fine,a.cognome,a.nome,a.sesso,cl.anno,cl.sezione')
        ->join('rc.alunno', 'a')
        ->join('a.classe', 'cl')
        ->join('rc.colloquio', 'c')
        ->join('c.orario', 'o')
        ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
        ->where('rc.data=:data AND rc.stato=:stato AND c.docente=:docente')
        ->orderBy('c.ora,cl.anno,cl.sezione,a.cognome,a.nome', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'docente' => $docente])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->join('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
        ->join('avc.classe', 'cl')
        ->join('AppBundle:Cattedra', 'c', 'WHERE', 'c.classe=cl.id')
        ->where('a.destinatariDocenti=:destinatario AND a.tipo=:tipo AND a.data=:data AND c.docente=:docente AND c.attiva=:attiva')
        ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'data' => $data->format('Y-m-d'),
          'docente' => $docente, 'attiva' => 1])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->where('a.docente=:docente AND a.tipo=:tipo AND a.data=:data')
        ->setParameters(['docente' => $docente, 'tipo' => 'V', 'data' => $data->format('Y-m-d')])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $k=>$v) {
        $dati['verifiche'][$k] = $this->bac->dettagliAvviso($v);
        // edit
        if ($this->azioneEvento('edit', $v->getData(), $docente, $v)) {
          // pulsante edit
          $dati['verifiche'][$k]['azioni']['edit'] = 1;
        }
        // delete
        if ($this->azioneEvento('delete', $v->getData(), $docente, $v)) {
          // pulsante delete
          $dati['verifiche'][$k]['azioni']['delete'] = 1;
        }
      }
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Controlla se è possibile eseguire l'azione specificata relativamente agli eventi.
   *
   * @param string $azione Azione da controllare
   * @param \DateTime $data Data dell'evento
   * @param Docente $docente Docente che esegue l'azione
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return bool Restituisce vero se l'azione è permessa
   */
  public function azioneEvento($azione, \DateTime $data, Docente $docente, Avviso $avviso=null) {
    if ($azione == 'add') {
      // azione di creazione
      if (!$avviso) {
        // nuovo avviso
        if ($data >= new \DateTime('today')) {
          // data non in passato, ok
          return true;
        }
      }
    } elseif ($azione == 'edit') {
      // azione di modifica
      if ($avviso) {
        // esiste avviso
        if ($data >= new \DateTime('today')) {
          // data non in passato
          if ($docente->getId() == $avviso->getDocente()->getId()) {
            // stesso docente: ok
            return true;
          }
        }
      }
    } elseif ($azione == 'delete') {
      // azione di cancellazione
      if ($avviso) {
        // esiste avviso
        if ($docente->getId() == $avviso->getDocente()->getId()) {
          // stesso docente: ok
          return true;
        }
      }
    }
    // non consentito
    return false;
  }

  /**
   * Controlla la presenza di altre verifiche nello stesso giorno
   *
   * @param Avviso $avviso Avviso su cui eseguire l'azione
   *
   * @return Array Dati formattati come array associativo
   */
  public function controlloVerifiche(Avviso $avviso) {
    // verifiche in stessa classe e stessa data
    $verifiche = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->join('c.classe', 'cl')
      ->where('a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
      ->setParameters(['tipo' => 'V', 'data' => $avviso->getData()->format('Y-m-d'),
        'classe' => $avviso->getCattedra()->getClasse()])
      ->orderBy('cl.anno,cl.sezione', 'ASC');
    if ($avviso->getId()) {
      // modifica di avviso esistente
      $verifiche = $verifiche
        ->andWhere('a.id!=:avviso')
      ->setParameter('avviso', $avviso->getId());
    }
    $verifiche = $verifiche
      ->getQuery()
      ->getResult();
    // restituisce dati
    return $verifiche;
  }

  /**
   * Crea l'annotazione sul registro in base ai dati dell'avviso
   *
   * @param Avviso $avviso Avviso di cui recuperare i dati
   */
  public function creaAnnotazione(Avviso $avviso) {
    // crea annotazione
    $a = (new Annotazione())
      ->setData($avviso->getData())
      ->setTesto($avviso->getOggetto()."\n".$avviso->getTesto())
      ->setVisibile(false)
      ->setAvviso($avviso)
      ->setClasse($avviso->getCattedra()->getClasse())
      ->setDocente($avviso->getDocente());
    $this->em->persist($a);
    $avviso->addAnnotazione($a);
  }

  /**
   * Restituisce la lista delle date dei giorni festivi.
   * Non sono considerate le assemblee di istituto (non sono giorni festivi).
   * Sono esclusi i giorni che precedono o seguono il periodo dell'anno scolastico.
   * Non sono indicati i riposi settimanali (domenica ed eventuali altri).
   *
   * @return string Lista di giorni festivi come stringhe di date
   */
  public function festivi() {
    // query
    $lista = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo')
      ->setParameters(['tipo' => 'F'])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    // crea lista date
    $lista_date = '';
    foreach ($lista as $f) {
      $lista_date .= ',"'.$f->getData()->format('d/m/Y').'"';
    }
    return '['.substr($lista_date, 1).']';
  }

  /**
   * Recupera i dati degli eventi per l'alunno indicato relativamente al mese indicato
   *
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $mese Mese di riferemento degli eventi da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function agendaEventiGenitori(Alunno $alunno, $mese) {
    $dati = null;
    // colloqui
    $colloqui = $this->em->getRepository('AppBundle:RichiestaColloquio')->createQueryBuilder('rc')
      ->where('rc.stato=:stato AND rc.alunno=:alunno AND MONTH(rc.data)=:mese')
      ->orderBy('rc.data', 'ASC')
      ->setParameters(['stato' => 'C', 'alunno' => $alunno, 'mese' => $mese->format('n')])
      ->getQuery()
      ->getResult();
    foreach ($colloqui as $c) {
      $dati[intval($c->getData()->format('j'))]['colloqui'] = 1;
    }
    // attivita
    $attivita = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->join('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
      ->join('avc.classe', 'cl')
      ->where('a.destinatariGenitori=:destinatario AND a.tipo=:tipo AND MONTH(a.data)=:mese AND cl.id=:classe')
      ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'mese' => $mese->format('n'),
        'classe' => $alunno->getClasse()])
      ->getQuery()
      ->getResult();
    foreach ($attivita as $a) {
      $dati[intval($a->getData()->format('j'))]['attivita'] = 1;
    }
    // verifiche
    $verifiche = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
      ->join('a.cattedra', 'c')
      ->leftJoin('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
      ->leftJoin('avi.alunno', 'al')
      ->where('a.tipo=:tipo AND MONTH(a.data)=:mese AND c.classe=:classe')
      ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
      ->setParameters(['tipo' => 'V', 'mese' => $mese->format('n'), 'classe' => $alunno->getClasse(),
        'no_destinatario' => 0, 'alunno' => $alunno])
      ->getQuery()
      ->getResult();
    foreach ($verifiche as $v) {
      $dati[intval($v->getData()->format('j'))]['verifiche'] = 1;
    }
    // festività
    $festivi = $this->em->getRepository('AppBundle:Festivita')->createQueryBuilder('f')
      ->where('f.sede IS NULL AND f.tipo=:tipo AND MONTH(f.data)=:mese')
      ->setParameters(['tipo' => 'F', 'mese' => $mese->format('n')])
      ->orderBy('f.data', 'ASC')
      ->getQuery()
      ->getResult();
    foreach ($festivi as $f) {
      $dati[intval($f->getData()->format('j'))]['festivo'] = 1;
    }
    // restituisce dati
    return $dati;
  }

  /**
   * Recupera i dettagli degli eventi per il docente indicato relativamente alla data indicata
   *
   * @param Alunno $alunno Alunno a cui sono indirizzati gli eventi
   * @param \DateTime $data Data di riferemento degli eventi da recuperare
   * @param string $tipo Tipo di evento da recuperare
   *
   * @return Array Dati formattati come array associativo
   */
  public function dettagliEventoGenitore(Alunno $alunno, $data, $tipo) {
    $dati = null;
    if ($tipo == 'C') {
      // colloqui
      $dati['colloqui'] = $this->em->getRepository('AppBundle:RichiestaColloquio')->createQueryBuilder('rc')
        ->select('rc.messaggio,so.inizio,so.fine,d.cognome,d.nome,d.sesso')
        ->join('rc.colloquio', 'c')
        ->join('c.docente', 'd')
        ->join('c.orario', 'o')
        ->join('AppBundle:ScansioneOraria', 'so', 'WHERE', 'so.orario=o.id AND so.giorno=c.giorno AND so.ora=c.ora')
        ->where('rc.data=:data AND rc.stato=:stato AND rc.alunno=:alunno')
        ->orderBy('c.ora', 'ASC')
        ->setParameters(['data' => $data->format('Y-m-d'), 'stato' => 'C', 'alunno' => $alunno])
        ->getQuery()
        ->getArrayResult();
    } elseif ($tipo == 'A') {
      // attività
      $attivita = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->join('AppBundle:AvvisoClasse', 'avc', 'WHERE', 'avc.avviso=a.id')
        ->join('avc.classe', 'cl')
        ->where('a.destinatariGenitori=:destinatario AND a.tipo=:tipo AND a.data=:data AND cl.id=:classe')
        ->setParameters(['destinatario' => 1, 'tipo' => 'A', 'data' => $data->format('Y-m-d'),
          'classe' => $alunno->getClasse()])
        ->getQuery()
        ->getResult();
      foreach ($attivita as $a) {
        $dati['attivita'][] = $this->bac->dettagliAvviso($a);
      }
    } elseif ($tipo == 'V') {
      // verifiche
      $verifiche = $this->em->getRepository('AppBundle:Avviso')->createQueryBuilder('a')
        ->join('a.cattedra', 'c')
        ->leftJoin('AppBundle:AvvisoIndividuale', 'avi', 'WHERE', 'avi.avviso=a.id')
        ->leftJoin('avi.alunno', 'al')
        ->where('a.tipo=:tipo AND a.data=:data AND c.classe=:classe')
        ->andWhere('a.destinatariIndividuali=:no_destinatario OR al.id=:alunno')
        ->setParameters(['tipo' => 'V', 'data' => $data->format('Y-m-d'), 'classe' => $alunno->getClasse(),
          'no_destinatario' => 0, 'alunno' => $alunno])
        ->getQuery()
        ->getResult();
      foreach ($verifiche as $v) {
        $dati['verifiche'][] = $this->bac->dettagliAvviso($v);
      }
    }
    // restituisce dati
    return $dati;
  }

}

