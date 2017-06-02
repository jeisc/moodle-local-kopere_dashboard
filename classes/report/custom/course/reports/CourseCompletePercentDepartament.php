<?php

namespace local_kopere_dashboard\report\custom\course\reports;

use local_kopere_dashboard\html\DataTable;
use local_kopere_dashboard\html\TableHeaderItem;
use local_kopere_dashboard\report\custom\ReportInterface;
use local_kopere_dashboard\util\Header;

class CourseCompletePercentDepartament implements ReportInterface
{

    public $reportName = 'Progresso com percentual de conclusão por Cargo e CPF';

    /**
     * @return string
     */
    public function name ()
    {
        return $this->reportName;
    }

    /**
     * @return boolean
     */
    public function isEnable ()
    {
        return true;
    }

    /**
     * @return void
     */
    public function generate ()
    {
        $courseid = optional_param ( 'courseid', 0, PARAM_INT );
        if ( $courseid == 0 )
            $this->listCourses ();
        else {
            echo '<script> document.body.className += " menu-w-90"; </script>';
            $this->createReport ();
        }
    }

    private function listCourses ()
    {
        echo '<h3>Selecione o curso para gerar o relatório</h3>';

        $table = new DataTable();
        $table->addHeader ( '#', 'id', TableHeaderItem::TYPE_INT, null, 'width: 20px' );
        $table->addHeader ( 'Nome do Curso', 'fullname' );
        $table->addHeader ( 'Nome curto', 'shortname' );
        $table->addHeader ( 'Visível', 'visible', TableHeaderItem::RENDERER_VISIBLE );
        $table->addHeader ( 'Nº alunos inscritos', 'inscritos', TableHeaderItem::TYPE_INT, null, 'width:50px;white-space:nowrap;' );

        $table->setAjaxUrl ( 'Courses::loadAllCourses' );
        $table->setClickRedirect ( '?Reports::loadReport&type=course&report=CourseCompletePercentDepartament&courseid={id}', 'id' );
        $table->printHeader ();
        $table->close ();
    }

    /**
     * @return void
     */
    public function createReport ()
    {
        global $DB;

        $courseid = optional_param ( 'courseid', 0, PARAM_INT );
        if ( $courseid == 0 )
            Header::notfound ( 'CourseID inválido!' );

        $reportSql
            = "SELECT ue.id,
                      u.id AS userid,
                      u.firstname, u.lastname, u.alternatename, u.middlename, u.lastnamephonetic, u.firstnamephonetic,
                      u.department,
                    
                      c.id AS courseid,
                      c.fullname, 
                      ue.timecreated,
                      
                      IFNULL((SELECT COUNT(gg.finalgrade) 
                        FROM {grade_grades} AS gg 
                        JOIN {grade_items} AS gi ON gg.itemid=gi.id
                        WHERE gi.courseid=c.id
                          AND gg.userid=u.id
                          AND gi.itemtype='mod'
                          GROUP BY u.id,c.id),'0') AS 'activities_completed',
                     
                      IFNULL((SELECT COUNT( gi.itemname ) 
                        FROM {grade_items} AS gi 
                        WHERE gi.courseid = c.id
                         AND gi.itemtype='mod'), '0') AS 'activities_assigned',
                         
                     
                      -- /*If activities_completed = activities_assigned, show date of last log entry. Otherwise, show percentage complete. If activities_assigned = 0, show 'n/a'.--*/
                      (
                          SELECT IF(`activities_assigned`!='0', (
                              SELECT IF( `activities_completed` = `activities_assigned`, 
                              (
                                  SELECT CONCAT('100% completo',FROM_UNIXTIME(MAX(log.time),'%m/%d/%Y'))
                                    FROM {log} log
                                   WHERE log.course = c.id
                                     AND log.userid = u.id
                              ), 
                              -- /*--Percent completed--*/
                              (
                                  SELECT CONCAT(IFNULL(ROUND((`activities_completed`)/(`activities_assigned`)*100,0), '0'),'% complete')))), 'n/a')
                      ) AS 'course_completed',
                    
                      cc.timecompleted AS 'concluido_em'
                     
                      FROM {user}                 u
                      JOIN {user_enrolments}      ue ON ue.userid=u.id
                      JOIN {enrol}                e   ON e.id=ue.enrolid
                      JOIN {course}               c   ON c.id = e.courseid
                      JOIN {context}              ctx ON ctx.instanceid = c.id
                      JOIN {role_assignments}     ra  ON ra.contextid = ctx.id
                      JOIN {role}                 r   ON r.id = e.roleid
                      JOIN {course_completions}   cc  ON c.id = cc.course AND u.id = cc.userid
                    
                     WHERE ra.userid      = u.id
                       AND ctx.instanceid = c.id
                       AND ra.roleid      = 5
                       AND c.visible      = 1
                       AND c.id           = :courseid";

        $reports = $DB->get_records_sql ( $reportSql, array( 'courseid' => $courseid ) );

        $report = array();
        foreach ( $reports as $item ) {

            $item->userfullname = fullname ( $item );


            $DB->get_field ( 'user_info_field', 'id', array( 'shortname' => 'Cargo' ) );

            $item->cpf   = $DB->get_field ( 'user_info_data', 'data', array(
                'fieldid' => $DB->get_field ( 'user_info_field', 'id', array( 'shortname' => 'cpf' ) ),
                'userid'  => $item->userid
            ) );
            $item->cargo = $DB->get_field ( 'user_info_data', 'data', array(
                'fieldid' => $DB->get_field ( 'user_info_field', 'id', array( 'shortname' => 'Cargo' ) ),
                'userid'  => $item->userid
            ) );

            $report[] = $item;
        }

        $table = new DataTable();
        $table->setTableId ( 'datatable_alunos' );

        $table->addHeader ( '#', 'id', TableHeaderItem::TYPE_INT, null, 'width: 20px' );
        $table->addHeader ( 'Aluno', 'userfullname' );
        $table->addHeader ( 'CPF', 'cpf' );
        $table->addHeader ( 'Cargo', 'cargo' );
        $table->addHeader ( 'Departamento', 'department' );
        $table->addHeader ( 'Curso', 'fullname' );
        $table->addHeader ( 'Data da inscrição', 'timecreated', TableHeaderItem::RENDERER_DATE );
        $table->addHeader ( 'Atividades Concluídas', 'activities_completed', TableHeaderItem::TYPE_INT );
        $table->addHeader ( 'Atividades Atribuídas', 'activities_assigned', TableHeaderItem::TYPE_INT );
        $table->addHeader ( 'Completo', 'course_completed' );
        $table->addHeader ( 'Conclusão em', 'concluido_em', TableHeaderItem::RENDERER_DATE );

        $table->setIsExport ( true );
        //$table->setClickRedirect ( '?Users::details&userid={userid}', 'userid' );
        $table->printHeader ( '', false );
        $table->setRow ( $report );
        $table->close ( false );

        //$pieData   = array();
        //$pieData[] = new Pie( "Cursos com Grupos", count ( $report ) );
        //$pieData[] = new Pie( "Cursos sem Grupos", Courses::countAll () - count ( $report ) );
        //
        // Pie::createRegular ( $pieData );
    }

    public function listData ()
    {

    }
}