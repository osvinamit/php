<?php
class EmarController extends Zend_Controller_Action{
	
	public function init(){
 		$this->SuperModel = new Application_Model_SuperModel();
		$this->view->SuperModel = new Application_Model_SuperModel();
		$this->modelEmail = new Application_Model_Email();
		if($this->view->user){
			$Front_User = Zend_Session::namespaceGet(DEFAULT_AUTH_NAMESPACE);
			$Front_User['storage']->visiting_user_id=$this->view->user->agency_id;
		}
		global $healthSession,$systemusers,$pharmacyusers;
		$layout=setSpecificLayout();
		if(!empty($layout)){
			$this->_helper->layout->setLayout($layout);
		}
		if($this->view->user->agency_user_type=="Pharmacy" || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id==0) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->is_subuser=='1')){
			$indData=$this->SuperModel->Super_Get("_uhs_agency","agency_user_agency_id=".$this->view->user->agency_id." and agency_user_type='Individual'");
			if(empty($indData)){
				 global $healthSession;
		  		 $healthSession->errorMsg="Please add indvidual before creating POS, MAR or TAR";
				 if($this->view->user->agency_user_type=="Pharmacy"){
				 	$this->_helper->getHelper("Redirector")->gotoRoute(array(),"front_pharmacy_add_individual");
				 }
				 else{
					$this->_helper->getHelper("Redirector")->gotoRoute(array(),"front_agency_add_individual");
				 }
			}
		}
		else if(in_array($this->view->user->agency_user_type,$systemusers)){
			$indIds=isSharedModule("individual");
			if(empty($indIds)){
				global $healthSession;
		  		$healthSession->errorMsg="No Individual has been shared with you.";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"front_agency_dashboard");
			}
		}
		else if(in_array($this->view->user->agency_user_type,$pharmacyusers)){
			$indIds=isSharedModule("individual");
			if(empty($indIds)){
				global $healthSession;
		  		$healthSession->errorMsg="No Individual has been shared with you.";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"front_pharmacy_dashboard");
			}
		}
 	}
	
	public function indexAction(){
		global $healthSession;
		$this->view->pageHeading="eMAR Suit";
	}	
	
	/* POS Mgmt. */
	public function posAction(){
		global $healthSession;
		$this->view->pageHeading="Physician Order Sheets";
		$icode=$this->_getParam("icode");
		$this->view->icode=$icode;
	}
	
	public function getposAction(){ 
		global $healthSession,$systemusers,$pharmacyusers;
		$icode=$this->_getParam('icode');
		$pindData=array();
		if($icode!=""){
			$pindData=$this->SuperModel->Super_Get("_uhs_agency","agency_code='".$icode."'","fetch");
		}
		$this->dbObj = Zend_Registry::get('db');
		$aColumns = array('pos_id','pos_number','pos_patient_fname','pos_patient_lname','pos_email','pos_published_status','pos_status','pos_created_by','pos_agent_id','pos_added_date','pos_updated_date','pos_modified_by');
		$sIndexColumn = 'pos_id';
		$sTable = '_uhs_pos';
		$sLimit = "";
		$subuser=$dstarts=$dends="";
		if(isset($_REQUEST['subuser']) && !empty($_REQUEST['subuser'])){
			$subuser=$_REQUEST['subuser'];
		}
		if(isset($_REQUEST['dstarts']) && !empty($_REQUEST['dstarts'])){
			$dstarts=$_REQUEST['dstarts'];
		}
		if(isset($_REQUEST['dends']) && !empty($_REQUEST['dends'])){
			$dends=$_REQUEST['dends'];
		}
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' ){
			$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".intval( $_GET['iDisplayLength'] );
		}
		$sOrder = "";
		if ( isset( $_GET['iSortCol_0'] ) ){
			$sOrder = "ORDER BY  ";
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ ){
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" ){
					$sOrder .= "".$aColumns[ intval( $_GET['iSortCol_'.$i] ) ]." ".
						($_GET['sSortDir_'.$i]==='asc' ? 'asc' : 'desc') .", ";
				}
			}
			
			$sOrder = substr_replace( $sOrder, "", -2 );
			if ( $sOrder == "ORDER BY" ){
				$sOrder = "";
			}
		}
		$sWhere = "";
		if ( isset($_GET['sSearch']) and $_GET['sSearch'] != "" ){
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ ){
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET["sSearch"]."%' OR "; // NEW CODE
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		for ( $i=0 ; $i<count($aColumns) ; $i++ ){
			if ( isset($_GET['bSearchable_'.$i]) and $_GET['bSearchable_'.$i] == "true" and $_GET['sSearch_'.$i] != '' ){
				if ( $sWhere == "" ){
					$sWhere = "WHERE ";
				}
				else{
					$sWhere .= " AND ";
				}
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch_'.$i]."%' ";
			}
		}
		$indIds="";
		if($icode!="" && !empty($pindData)){
			$indIds=$pindData['agency_id'];
		}
		else{
			$indData=$this->SuperModel->Super_Get("_uhs_agency","agency_user_agency_id=".$this->view->user->agency_id." and agency_user_type='Individual'","fetchAll",array("fields"=>"agency_id"));
			if(count($indData)>0){
				$indIds=implode_r(",",$indData);
				$sharedIds=isSharedModule("emar-individual");
				if(!empty($sharedIds)){
					$indIds.=','.isSharedModule("emar-individual");
				}
			}
			else{
				$indIds=isSharedModule("emar-individual");
			}
		}
		if($sWhere){
			$idWhere="pos_agent_id=0";
			if(!empty($indIds)){
				$idWhere="pos_agent_id IN(".$indIds.")";
			}
			$sWhere.=" and pos_status='1' and ".$idWhere; 
		}
		else{
			$idWhere="pos_agent_id=0";
			if(!empty($indIds)){
				$idWhere="pos_agent_id IN(".$indIds.")";
			}
			$sWhere.=" where pos_status='1' and ".$idWhere; 
		}
		
		if(!empty($dstarts)){
			$sWhere.=" and DATE(pos_added_date) >='".$dstarts."'";
		}
		if(!empty($dends)){
			$sWhere.=" and DATE(pos_added_date) <='".$dends."'";
		}
		
		$sQuery = "SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $aColumns))." from _uhs_pos join _uhs_agency on _uhs_pos.pos_agent_id=_uhs_agency.agency_id  $sWhere $sOrder $sLimit";
 		$qry = $this->dbObj->query($sQuery)->fetchAll();
		$sQuery = "SELECT FOUND_ROWS() as fcnt";
		$aResultFilterTotal =  $this->dbObj->query($sQuery)->fetchAll(); 
		$iFilteredTotal = $aResultFilterTotal[0]['fcnt'];
		$sQuery = "SELECT COUNT(`".$sIndexColumn."`) as cnt FROM $sTable ";
		$rResultTotal = $this->dbObj->query($sQuery)->fetchAll(); 
		$iTotal = $rResultTotal[0]['cnt'];
		$output = array(
 				"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
			);
		$j=0;
		foreach($qry as $row1){
			$modifierData=array();
			if($row1['pos_modified_by']!=0){
				$modifierData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_modified_by']);
			}
			$disClick='href="'.APPLICATION_URL.'/medication-discontinue/'.$row1[$sIndexColumn].'"';
			$editClick='href="'.APPLICATION_URL.'/edit-physician-order-sheet/'.$row1[$sIndexColumn].'"';
			$viewClick='href="'.APPLICATION_URL.'/view-physician-order-sheet/'.$row1[$sIndexColumn].'"';
			if(!isset($healthSession->emarSecurity) && $healthSession->emarSecurity!=1 && ($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers))){
				$disClick='onclick="securityCheck(3,'.$row1[$sIndexColumn].');"';
				$editClick='onclick="securityCheck(1,'.$row1[$sIndexColumn].');"';
				$viewClick='onclick="securityCheck(2,'.$row1[$sIndexColumn].');"';
			}
			$creatorData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_created_by']);
			$permissions=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_id);
			$editLink=$pos_discontinue_reason_val=''; $disClass="disabled=disabled";
			if(in_array($this->view->user->agency_user_type,$systemusers)){
				$parentPermission=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_user_agency_id);
				if(($parentPermission=="Both" || $parentPermission=="Edit") && ($permissions=="Both" || $permissions=="Edit")){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-times-circle"></i> Discontinue Medication</a>';
				}
			}
			else{
				if($permissions=="Both" || $permissions=="Edit"){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-times-circle"></i> Discontinue Medication</a>';	
				}
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$disClass="";
			}
			$row=array();
 			$row[] = $j+1;
			$row[]='<input class="elem_ids checkboxes" '.$disClass.'  type="checkbox" name="'.$sTable.'['.$row1[$sIndexColumn].']"  value="'.$row1[$sIndexColumn].'">';
			$row[]=($row1['pos_number']);
  			$row[]=ucwords($row1['pos_patient_fname']." ".$row1['pos_patient_lname']);
			$row[]=$row1['pos_email']; 
			$userType=printUserType($creatorData);
			$row[]=ucwords($creatorData['agency_first_name']." ".$creatorData['agency_last_name'])."<br/>".$userType;  
			$row[]=formatDateTimeNew($row1['pos_added_date']);
			if(!empty($modifierData)){
				$userType=printUserType($modifierData);
				$row[]=ucwords($modifierData['agency_first_name']." ".$modifierData['agency_last_name'])."&nbsp;".$userType;
				$row[]=formatDateTime($row1['pos_updated_date']);
			}     
			else{
				$row[]="-";
				$row[]="-";
			}
			$publishUrl="";
			if($row1['pos_published_status']==0){
				$row[]='<span class="badge badge-danger">No</span>';
				if(checkEmarCreationPermission($row1['pos_agent_id'])){
					$publishUrl='&nbsp; <a class="btn btn-xs btn-default" style="margin-bottom:4px;" href="'.APPLICATION_URL.'/emar/publish/type/pos/pos_id/'.$row1[$sIndexColumn].'"><i class="fa fa-bullseye"></i> Publish </a>';
				}
			}
			else{
				$row[]='<span class="badge badge-success">Yes</span>';
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$editLink='<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$editClick.'><i class="fa fa-edit"></i> Edit </a>';
			}
			$row[]=$editLink.$publishUrl.'&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$viewClick.'><i class="fa fa-search"></i> View </a>'.$pos_discontinue_reason_val.'';
			
 			$output['aaData'][] = $row;
			$j++;
		}
		echo json_encode( $output );
		exit();
	}
	
	public function addposAction(){
		global $healthSession,$systemusers,$pharmacyusers; 
		$this->view->pageHeading="Add Physician Order Sheet";
		$agents=$this->SuperModel->GetIndivisuals($this->view->user->agency_id,1);
		if(empty($agents)){
			$healthSession->errorMsg = "No individual assigned to you with edit permission.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		if(count($agents)==1){
			$healthSession->errorMsg = "No more individual exists or subscription of individuals has not been completed.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
 		$form = new Application_Form_Emar();
		$form->posform($this->view->user->agency_id,'',1,'1');
 		if($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost();	
                       // prd($data);
   			if($form->isValid($data)){
				unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
				//------------06.11.18------------------------
				$madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //-----------------------------------------------
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_prescription_routine']=array_values($data['pos_prescription_routine']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_prescription_treatment']=array_values($data['pos_prescription_treatment']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				
				$madicationData['feq_day']=array_values($data['feq_day']);
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
				unset($data['feq_day']);
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);
				
				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_prescription_routine']);
				unset($data['pos_medication_prn']);
				unset($data['pos_prescription_treatment']);
				unset($data['pos_prescription_sideeffect']);
				unset($data['agent_mar_status']);
				unset($data['agent_tar_status']);
				 //------------06.11.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//----------------------------------------
				
				$totalmadication=count($madicationData['pos_medication_name']);
				
				$data['pos_created_by']=$this->view->user->subuser_id; //$this->view->user->agency_id;
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto'])){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_added_date']=date('Y-m-d H:i:s');
				$data['pos_number']=getRandomString(6,'alpha');
				$data['pos_status']=1;
				
				$get_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and mar_status="1" and pos_status="0"');
				if(empty($get_pos)){
					$data['mar_status']=1;
				}
				$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and tar_status="1" and pos_status="0"');
				if(empty($get_data_pos)){
					$data['tar_status']=1;
				}
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data);
				$insertedId=$s->inserted_id;
			
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
					//------------06.11.18-------------------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//----------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_medication_reminder']=$remindVal;
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_prescription_routine']=$madicationData['pos_prescription_routine'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_prescription_treatment']=$madicationData['pos_prescription_treatment'][$i];
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					
					$madicationDatanew['medication_pos_id']=$insertedId;
					$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
                                        
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array(
										'umt_medication_id'=>$a->inserted_id,
										'umt_freq_id'=>$isIns->inserted_id,
										'umt_time'=>$timeString,
										'is_umt_prn'=>$isPrn,
										'umt_added'=>date("Y-m-d H:i:s")
								);
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily"){
						$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$a->inserted_id,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
                                        if($madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$a->inserted_id,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
				  }
				if($this->view->user->agency_user_type="Pharmacy" || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id==0)){
				  $healthSession->successMsg=" POS (Physician Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been added successfully";
				}
				else{
				 if((in_array($this->view->user->agency_user_type,$systemusers) || in_array($this->view->user->agency_user_type,$pharmacyusers) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id!=0)) && checkNotifySettings($this->view->user->agency_user_agency_id,"pos")){
					$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>"pos","notification_type_id"=>$insertedId,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
					$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);	
				 }
				 $healthSession->successMsg=" POS (Physician Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been added successfully and it would be shown after publish.";	
				}
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
	}
	
	// public function editposAction(){
	// 	// echo "edit";die;

	// 	global $healthSession,$systemusers,$pharmacyusers; 
	// 	if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
	// 		if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
	// 			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
	// 		}
	// 	}
	// 	$this->view->pageHeading="Update Physician Order Sheet";
 // 		$form = new Application_Form_Emar();
	// 	$pos_id=$this->_getParam('pos_id'); 
	// 	$this->view->pos_id=$pos_id;
	// 	$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
	// 	if(empty($po_data)){
	// 		 $healthSession->errorMsg="No Record Found.";
	// 		 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
	// 	}
	// 	$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
	// 	$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
	// 	$permission=checkIndividualPermissions($indi_data['agency_id']);
	// 	if(empty($permission)){
	// 		 global $healthSession;
	// 		 $healthSession->errorMsg="No Individual Found.";
	// 		 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
	// 	}
		
	// 	$form->posform($this->view->user->agency_id,$pos_id,1,1);
	// 	if($po_data['pos_patient_dob']!="0000-00-00" && !empty($po_data['pos_patient_dob'])){
	// 		$po_data['pos_patient_dob']=date("m/d/Y",strtotime($po_data['pos_patient_dob']));
	// 	}
	// 	if($po_data['pos_admission_date']!="0000-00-00" && !empty($po_data['pos_admission_date'])){
	// 		$po_data['pos_admission_date']=date("m/d/Y",strtotime($po_data['pos_admission_date']));
	// 	}
	// 	if($po_data['pos_charting_from']!="0000-00-00" && $po_data['pos_charting_from']!=NULL){
	// 		$po_data['pos_charting_fromto']=date('m/d/Y',strtotime($po_data['pos_charting_from'])).'-'.date('m/d/Y',strtotime($po_data['pos_charting_to']));
	// 	}
		
	// 	$form->populate($po_data);
	// 	$form->agent_mar_status->setValue(0);
	// 	$form->agent_tar_status->setValue(0);
	// 	$get_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$po_data['pos_agent_id'].'" and mar_status="1" and pos_status="0"');
	// 	if(!empty($get_pos)){
	// 		$form->agent_mar_status->setValue(1);
	// 	}
	// 	$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$po_data['pos_agent_id'].'" and tar_status="1" and pos_status="0"');
	// 	if(!empty($get_data_pos)){
	// 		$form->agent_tar_status->setValue(1);
	// 	}
		
	// 	// $uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."'",'fetchAll',array("fields"=>"*"));
	// 	$uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetchAll',array("fields"=>"*"));
	// 	//------------------------------------23.10.18---------------------------------------------
	// 	foreach ($uhs_pos_medicationdata as $key => $value) {
	// 		$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace('"', '”', $value['pos_medication_brand']);
	// 		$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace("'", '’', $value['pos_medication_brand']);
	// 		$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace("'", '’', $value['pos_medication_direction']);
	// 		$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace('"', '”', $value['pos_medication_direction']);
			
	// 	}
	// 	//----------------------------------------------------------------------------
	// 	$this->view->uhs_pos_medicationdata=$uhs_pos_medicationdata;
 // 		if($this->getRequest()->isPost()){
	// 		$data=$this->getRequest()->getPost();
 //   			if($form->isValid($data)){
   				
	// 		    unset($data['bttnsubmit']);
	// 			$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
	// 			$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
	// 			$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
	// 			$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
	// 			$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
	// 			$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
	// 			$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
	// 			$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
	// 			//------------06.11.18------------------------
	// 			$madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
	// 		    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
	// 		    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
	// 		    //-----------------------------------------------	
	// 			$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
	// 			$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
	// 			$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
	// 			$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
	// 			$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
	// 			$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
	// 			$madicationData['pos_prescription_routine']=array_values($data['pos_prescription_routine']);
	// 			$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
	// 			$madicationData['pos_prescription_treatment']=array_values($data['pos_prescription_treatment']);
	// 			$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				
	// 			$madicationData['feq_day']=array_values($data['feq_day']);
	// 			$madicationData['freq_times']=array_values($data['freq_times']);
	// 			$madicationData['pos_control_medication']=array_values($data['pos_control_medication']);
	// 			$madicationData['pos_no_of_pills']=array_values($data['pos_no_of_pills']);
	// 			$madicationData['pos_custom_date']=array_values($data['pos_custom_date']);
	// 			$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
	// 			unset($data['feq_day']);
	// 			unset($data['freq_times']);
	// 			unset($data['pos_custom_date']);
	// 			unset($data['pos_medication_atime']);
	// 			unset($data['pos_medication_brand']);
	// 			unset($data['pos_medication_id']);
	// 			unset($data['pos_medication_name']);
	// 			unset($data['pos_medication_direction']);
	// 			unset($data['pos_medication_rx_number']);
	// 			unset($data['pos_medication_odate']);
	// 			unset($data['pos_medication_frequency_type']);
	// 			unset($data['pos_medication_atime']);
	// 			unset($data['pos_medication_reminder']);
	// 			unset($data['pos_pphysician_fname']);
	// 			unset($data['pos_pphysician_lname']);
	// 			unset($data['pos_pphysician_address']);
	// 			unset($data['pos_pphysician_phone']);
	// 			unset($data['pos_pphysician_email']);
	// 			unset($data['pos_prescription_routine']);
	// 			unset($data['pos_medication_prn']);
	// 			unset($data['pos_physician_refilno']);
	// 			unset($data['pos_prescription_treatment']);
	// 			unset($data['pos_prescription_sideeffect']);
	// 			unset($data['agent_mar_status']);
	// 			unset($data['agent_tar_status']);
	// 			 //------------06.11.18------------------------
	// 			unset($data['pos_add_blood_pressure']);
	// 			unset($data['pos_add_blood_sugar']);
	// 			unset($data['pos_add_bowel_movement']);
	// 			unset($data['pos_control_medication']);
	// 			unset($data['pos_no_of_pills']);
	// 			//----------------------------------------
				
	// 			$totalmadication=count($madicationData['pos_medication_name']);
				
	// 			if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
	// 				$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
	// 			}
	// 			if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
	// 				$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
	// 			}
	// 			if(!empty($data['pos_charting_fromto'])){
	// 				$fromto=explode("-",$data['pos_charting_fromto']);
	// 				$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
	// 				$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
	// 			}
	// 			unset($data['pos_charting_fromto']);
	// 			$data['pos_updated_date']=date('Y-m-d H:i:s');
	// 			$data['pos_modified_by']=$this->view->user->subuser_id;
	// 			if(empty($get_data_pos)){
	// 				$data['tar_status']="1";
	// 			}
	// 			if(empty($get_pos)){
	// 				$data['mar_status']="1";
	// 			}
	// 			$s=$this->SuperModel->Super_Insert("_uhs_pos",$data,'pos_id="'.$pos_id.'"');
	// 			$totalmadication=count($madicationData['pos_medication_name']);
				
	// 			for($i=0;$i<$totalmadication;$i++){
	// 				$madicationDatanew=array();
	// 				$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
	// 				$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
	// 				$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
	// 				$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
	// 				//------------06.11.18-------------------------------------
	// 				$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
	// 				$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
	// 				$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
	// 				//----------------------------------------------------------
	// 				$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
	// 				$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
	// 				$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
	// 				$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
	// 				$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
	// 				$remindVal=$madicationData['pos_medication_reminder'][$i];
	// 				$madicationDatanew['pos_medication_reminder']=$remindVal;
	// 				$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
	// 				$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
	// 				$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
	// 				$madicationDatanew['pos_prescription_routine']=$madicationData['pos_prescription_routine'][$i];
	// 				$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
	// 				$madicationDatanew['pos_prescription_treatment']=$madicationData['pos_prescription_treatment'][$i];
	// 				$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
	// 				$madicationDatanew['medication_pos_id']=$pos_id;
	// 				$madicationDatanew['pos_control_medication']=$madicationData['pos_control_medication'][$i];
	// 				$madicationDatanew['pos_custom_date']=$madicationData['pos_custom_date'][$i];
	// 				$madicationDatanew['pos_no_of_pills']=$madicationData['pos_no_of_pills'][$i];
					
	// 				//var_dump($madicationData['pos_medication_id']);
	// 				//die;

					
	// 				if($madicationData['pos_medication_id'][$i]!=""){
	// 					$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew,'medication_id='.$madicationData['pos_medication_id'][$i]);
	// 					$mid=$madicationData['pos_medication_id'][$i];
	// 				}
	// 				else{
	// 					$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
	// 					$mid=$a->inserted_id;

	// 				}
	// 				// die;
	// 				if($madicationData['pos_medication_id'][$i]!=""){
	// 					$isDel=$this->SuperModel->Super_Delete("_uhs_medication_frequencies","umt_freq_medication_id=".$madicationData['pos_medication_id'][$i]);
	// 					$isDel=$this->SuperModel->Super_Delete("_uhs_medication_times","umt_medication_id=".$madicationData['pos_medication_id'][$i]);
	// 					$isDel=$this->SuperModel->Super_Delete("_uhs_medication_custom_date","umt_medication_id=".$madicationData['pos_medication_id'][$i]);
	// 				}
	// 				if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
	// 					for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
	// 						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
	// 						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
	// 						if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
	// 							for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
	// 							$timeString="00:00:00";
	// 							$isPrn=0;
	// 							if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
	// 								$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
	// 							}
	// 							else{
	// 								$isPrn=1;
	// 							}
	// 							$time_data=array(
	// 									'umt_medication_id'=>$mid,
	// 									'umt_freq_id'=>$isIns->inserted_id,
	// 									'umt_time'=>$timeString,
	// 									'is_umt_prn'=>$isPrn,
	// 									'umt_added'=>date("Y-m-d H:i:s")
	// 							);
	// 							if($isPrn==1){
	// 								for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
	// 									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 								}
	// 							}
	// 							else{
	// 								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 							}
	// 						}
	// 						}
	// 					}
	// 				}
	// 				if($madicationDatanew['pos_medication_frequency_type']=="Daily"){
	// 					$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
	// 					$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
	// 					if(!empty($madicationData['pos_medication_atime'][$i])){
	// 						for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
	// 						$timeString="00:00:00";
	// 						$isPrn=0;
	// 						if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
	// 							$timeString=$madicationData['pos_medication_atime'][$i][$t];
	// 						}
	// 						else{
	// 							$isPrn=1;
	// 						}
	// 						$time_data=array(
	// 								'umt_medication_id'=>$mid,
	// 								'umt_freq_id'=>$isIns->inserted_id,
	// 								'umt_time'=>$timeString,
	// 								'is_umt_prn'=>$isPrn,
	// 								'umt_added'=>date("Y-m-d H:i:s")
	// 						);
	// 						if($isPrn==1){
	// 							for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
	// 								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 							}
	// 						}
	// 						else{
	// 							$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 						}
	// 					}
	// 					}
	// 				}
 //                    if($madicationDatanew['pos_medication_frequency_type']=="As Needed"){
	// 					$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
	// 					$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
	// 					if(!empty($madicationData['pos_medication_atime'][$i])){
	// 						for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
	// 						$timeString="00:00:00";
	// 						$isPrn=0;
	// 						if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
	// 							$timeString=$madicationData['pos_medication_atime'][$i][$t];
	// 						}
	// 						else{
	// 							$isPrn=1;
	// 						}
	// 						$time_data=array(
	// 								'umt_medication_id'=>$mid,
	// 								'umt_freq_id'=>$isIns->inserted_id,
	// 								'umt_time'=>$timeString,
	// 								'is_umt_prn'=>$isPrn,
	// 								'umt_added'=>date("Y-m-d H:i:s")
	// 						);
	// 						if($isPrn==1){
	// 							for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
	// 								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 							}
	// 						}
	// 						else{
	// 							$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
	// 						}
	// 					}
	// 					}
	// 				}
	// 				if($madicationDatanew['pos_medication_frequency_type']=="Custom"){
	// 					$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_custom_date"=>$madicationData['pos_custom_date'][$i]);
	// 					$isIns=$this->SuperModel->Super_Insert("_uhs_medication_custom_date",$freqArr);
						
	// 				}
                                        
	// 			}
	// 			$healthSession->successMsg=" POS (Physician Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been updated successfully";
	// 			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
	// 		}else{
	// 			$healthSession->errorMsg = "Please check information again.";
 // 			}
	// 	 }
 //  		 $this->view->form =$form;
	// 	 $this->render('addpos');
	// }
	public function editposAction(){
		global $healthSession,$systemusers,$pharmacyusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
			}
		}
		$this->view->pageHeading="Update Physician Order Sheet";
 		$form = new Application_Form_Emar();
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		
		$form->posform($this->view->user->agency_id,$pos_id,1,1);
		if($po_data['pos_patient_dob']!="0000-00-00" && !empty($po_data['pos_patient_dob'])){
			$po_data['pos_patient_dob']=date("m/d/Y",strtotime($po_data['pos_patient_dob']));
		}
		if($po_data['pos_admission_date']!="0000-00-00" && !empty($po_data['pos_admission_date'])){
			$po_data['pos_admission_date']=date("m/d/Y",strtotime($po_data['pos_admission_date']));
		}
		if($po_data['pos_charting_from']!="0000-00-00" && $po_data['pos_charting_from']!=NULL){
			$po_data['pos_charting_fromto']=date('m/d/Y',strtotime($po_data['pos_charting_from'])).'-'.date('m/d/Y',strtotime($po_data['pos_charting_to']));
		}
		
		$form->populate($po_data);
		$form->agent_mar_status->setValue(0);
		$form->agent_tar_status->setValue(0);
		$get_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$po_data['pos_agent_id'].'" and mar_status="1" and pos_status="0"');
		if(!empty($get_pos)){
			$form->agent_mar_status->setValue(1);
		}
		$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$po_data['pos_agent_id'].'" and tar_status="1" and pos_status="0"');
		if(!empty($get_data_pos)){
			$form->agent_tar_status->setValue(1);
		}
		
		// $uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."'",'fetchAll',array("fields"=>"*"));
		$uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetchAll',array("fields"=>"*"));
		//------------------------------------23.10.18---------------------------------------------
		foreach ($uhs_pos_medicationdata as $key => $value) {
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace('"', '”', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace("'", '’', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace("'", '’', $value['pos_medication_direction']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace('"', '”', $value['pos_medication_direction']);
			
		}
		//----------------------------------------------------------------------------
		$this->view->uhs_pos_medicationdata=$uhs_pos_medicationdata;
 		if($this->getRequest()->isPost()){
			$data=$this->getRequest()->getPost();
   			if($form->isValid($data)){
   				
			    unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
				//------------06.11.18------------------------
				$madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //-----------------------------------------------	
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_prescription_routine']=array_values($data['pos_prescription_routine']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_prescription_treatment']=array_values($data['pos_prescription_treatment']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				$madicationData['pos_control_medication']=array_values($data['pos_control_medication']);
				//$madicationData['pos_custom_date']=array_values($data['pos_custom_date']);
				$madicationData['pos_no_of_pills']=array_values($data['pos_no_of_pills']);
				
				$madicationData['feq_day']=array_values($data['feq_day']);
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
				unset($data['feq_day']);
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);

				//unset($data['pos_custom_date']);

				unset($data['pos_control_medication']);
				unset($data['pos_no_of_pills']);

				
				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_prescription_routine']);
				unset($data['pos_medication_prn']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_prescription_treatment']);
				unset($data['pos_prescription_sideeffect']);
				unset($data['agent_mar_status']);
				unset($data['agent_tar_status']);
				 //------------06.11.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//----------------------------------------
				
				$totalmadication=count($madicationData['pos_medication_name']);
				
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto'])){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_updated_date']=date('Y-m-d H:i:s');
				$data['pos_modified_by']=$this->view->user->subuser_id;
				if(empty($get_data_pos)){
					$data['tar_status']="1";
				}
				if(empty($get_pos)){
					$data['mar_status']="1";
				}
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data,'pos_id="'.$pos_id.'"');
				$totalmadication=count($madicationData['pos_medication_name']);
				
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
					//------------06.11.18-------------------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//----------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_medication_reminder']=$remindVal;
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_control_medication']=$madicationData['pos_control_medication'][$i];
					//$madicationDatanew['pos_custom_date']=$madicationData['pos_custom_date'][$i];
					$madicationDatanew['pos_no_of_pills']=$madicationData['pos_no_of_pills'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_prescription_routine']=$madicationData['pos_prescription_routine'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_prescription_treatment']=$madicationData['pos_prescription_treatment'][$i];
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					$madicationDatanew['medication_pos_id']=$pos_id;
					
					if($madicationData['pos_medication_id'][$i]!=""){
						
						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew,'medication_id='.$madicationData['pos_medication_id'][$i]);
						$mid=$madicationData['pos_medication_id'][$i];
					}
					else{

						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
						$mid=$a->inserted_id;

					}
					if($madicationData['pos_medication_id'][$i]!=""){
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_frequencies","umt_freq_medication_id=".$madicationData['pos_medication_id'][$i]);
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_times","umt_medication_id=".$madicationData['pos_medication_id'][$i]);
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array(
										'umt_medication_id'=>$mid,
										'umt_freq_id'=>$isIns->inserted_id,
										'umt_time'=>$timeString,
										'is_umt_prn'=>$isPrn,
										'umt_added'=>date("Y-m-d H:i:s")
								);
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily"){
						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$mid,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
                                        if($madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$mid,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
                                        
				}
				$healthSession->successMsg=" POS (Physician Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been updated successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
		 $this->render('addpos');
	}
	public function getposdetailsAction(){
		$med=$_REQUEST['med'];
		$day=$_REQUEST['day'];
		$freqData=$this->SuperModel->Super_get("_uhs_medication_frequencies","umt_freq_medication_id IN(".$med.") and umt_freq_days=".$day,'fetch');
		if(!empty($freqData)){
			$atimesData=$this->SuperModel->Super_get("_uhs_medication_times","umt_medication_id IN(".$med.") and umt_freq_id=".$freqData['umt_freq_id'],'fetchAll',array("fields"=>"umt_time"));
		}
		if(!empty($atimesData) && !empty($freqData)){
			echo json_encode($atimesData)."~~".json_encode($freqData);
		}
		else{
			echo "";
		}
		exit();
	}

	public function getcustomdateAction(){
		$freqData=$this->SuperModel->Super_get("_uhs_medication_frequencies","umt_freq_medication_id IN(".$med.") and umt_freq_days=".$day,'fetch');
		if(!empty($freqData)){
			$atimesData=$this->SuperModel->Super_get("_uhs_medication_times","umt_medication_id IN(".$med.") and umt_freq_id=".$freqData['umt_freq_id'],'fetchAll',array("fields"=>"umt_time"));
		}
		if(!empty($atimesData) && !empty($freqData)){
			echo json_encode($atimesData)."~~".json_encode($freqData);
		}
		else{
			echo "";
		}
		exit();
	}
	
	public function discontinueAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$this->view->pageHeading="Discontinue Medication ".ucwords($po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname'])."";
		$redirect_route="physician_order_sheet";
 		$form = new Application_Form_Emar();
		$avail_data=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetch',array('fields'=>array('medication_id')));
		$this->view->avail_data=$avail_data;
		 //davinder
		 $dis_avail_data=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetchall');
		 $current_date = date('Y-m-d');
		 $pos_discontinue_datetime = strtotime($dis_avail_data[0]['pos_discontinue_datetime']);
		 $pos_discontinue_datetime = date('Y-m-d', $pos_discontinue_datetime);
		 $pos_discontinue_datetime = strtotime($pos_discontinue_datetime);
		 $current_date = strtotime($current_date);
		 if($current_date == $pos_discontinue_datetime){
					$data['pos_discontinue_status']=1;
					$this->SuperModel->Super_Insert("_uhs_pos_medication",$data,"medication_id='".$dis_avail_data[0]['medication_id']."'");
					}
		//davinder
		 $form->pos_discontinue($pos_id);
		 $form->populate($po_data);
		 $users=array("_uhs_agency","agency_id=pos_discontinue_name",'left',array('agency_first_name','agency_last_name'));
		 $dis_continue_med=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='1'",'fetchAll',array('fields'=>array('*'),'group'=>'medication_id'),array(0=>$users));
		 $this->view->dis_continue_med=$dis_continue_med;
		if($this->getRequest()->isPost()) {
			$posted_data = $this->getRequest()->getPost();	
   			if($form->isValid($posted_data)){
				$data=$form->getValues();
				$med_id=$data['medication_id'];
				unset($data['medication_id']);
				$data['pos_discontinue_name']=$this->view->user->agency_id;
				if($current_date == $dis_avail_data){
					$data['pos_discontinue_status']=1;
					}else{
						$data['pos_discontinue_status']=0;
					}
				$po_data=$this->SuperModel->Super_Insert("_uhs_pos_medication",$data,"medication_id='".$med_id."'");
				$healthSession->successMsg="Discontinue for the medication is done successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),'physician_order_sheet');
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
	}
	
	public function viewposAction(){ 
		global $healthSession,$systemusers,$pharmacyusers;
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$edit=$this->_getParam('edit'); 
		$this->view->pos_id=$pos_id;
		$this->view->edit=$edit;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$this->view->po_data=$po_data;
		$this->view->indi_parent_data=$indi_parent_data;
		$this->view->indi_data=$indi_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
                
		$med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$this->view->pageHeading="Physician Order Sheet for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->med_po_data=$med_po_data;
	}
	
	public function viewposreportAction(){
		ini_set('max_execution_time', 1000);
		ini_set('memory_limit', '512M');
		global $healthSession;
		$this->view->pageHeading="POS Report";
		$pos_id=$this->_getParam('pos_id'); 
		$edit=$this->_getParam('edit'); 
		$this->view->pos_id=$pos_id;
		$this->view->edit=$edit;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_discontinue_status='0' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_discontinue_status='0' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$this->view->pageHeading="Physician Order Sheet for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";

		$this->view->med_po_data=$med_po_data;
		$html = $this->view->render('emar/viewposreport.phtml');
		require_once(ROOT_PATH.'/private/mpdf/mpdf.php');
		$stylesheet=file_get_contents(APPLICATION_URL.'/public/plugins/bootstrap/css/bootstrap.min.css');
		$stylesheet1=file_get_contents(APPLICATION_URL.'/public/front_css/style_custom.css');
		$mpdf=new mPDF('utf-8','A3');
		$mpdf->SetTitle($this->view->site_configs['site_name']." | ".ucwords($indi_data['agency_first_name']." ".$indi_data['agency_last_name'])." | View POS");
		$mpdf->WriteHTML($stylesheet,1);
		$mpdf->WriteHTML($stylesheet1,1);
		//$mpdf->shrink_tables_to_fit = 1;
		$mpdf->WriteHTML($html,2);
//                $mpdf->WriteHTML("<body style='font-family: serif; font-size: 40pt;'>");
//                $mpdf->shrink_tables_to_fit=1;
        //$mpdf->keep_table_proportions = true;
        $mpdf->Output();
		exit();
	}
	
	/* MAR Mgmt. */
	public function marAction(){
		global $healthSession;
		$icode=$this->_getParam("icode");
		$this->view->icode=$icode;
		$this->view->pageHeading="Medication Administration Records";
	}
	
	public function getmardataAction(){
		global $healthSession,$systemusers,$pharmacyusers;
		$icode=$this->_getParam('icode');
		$pindData=array();
		if($icode!=""){
			$pindData=$this->SuperModel->Super_Get("_uhs_agency","agency_code='".$icode."'","fetch");
		}
 		$this->dbObj = Zend_Registry::get('db');
		$aColumns = array('pos_id','pos_number','pos_patient_fname','pos_patient_lname','pos_email','pos_published_status','pos_status','mar_status','pos_agent_id','pos_created_by','pos_added_date','pos_updated_date','pos_modified_by');
		$sIndexColumn = 'pos_id';
		$sTable = '_uhs_pos';
		if(isset($_REQUEST['dstarts']) && !empty($_REQUEST['dstarts'])){
			$dstarts=$_REQUEST['dstarts'];
		}
		if(isset($_REQUEST['dends']) && !empty($_REQUEST['dends'])){
			$dends=$_REQUEST['dends'];
		}
		$sLimit = "";
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' ){
			$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".intval( $_GET['iDisplayLength'] );
		}
		$sOrder = "";
		if (isset($_GET['iSortCol_0'])){
			$sOrder = "ORDER BY  ";
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder .= "".$aColumns[ intval( $_GET['iSortCol_'.$i] ) ]." ".
						($_GET['sSortDir_'.$i]==='asc' ? 'asc' : 'desc') .", ";
				}
			}
			
			$sOrder = substr_replace( $sOrder, "", -2 );
			if ( $sOrder == "ORDER BY" ){
				$sOrder = "";
			}
		}
		$sWhere = "";
		if ( isset($_GET['sSearch']) and $_GET['sSearch'] != "" ){
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET["sSearch"]."%' OR "; // NEW CODE
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		for ( $i=0 ; $i<count($aColumns) ; $i++ ){
			if ( isset($_GET['bSearchable_'.$i]) and $_GET['bSearchable_'.$i] == "true" and $_GET['sSearch_'.$i] != '' ){
				if ( $sWhere == "" ){
					$sWhere = "WHERE ";
				}
				else{
					$sWhere .= " AND ";
				}
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch_'.$i]."%' ";
			}
		}
		$indIds=0;
		if($icode!="" && !empty($pindData)){
			$indIds=$pindData['agency_id'];
		}
		else{
			$indData=$this->SuperModel->Super_Get("_uhs_agency","agency_user_agency_id=".$this->view->user->agency_id." and agency_user_type='Individual'","fetchAll",array("fields"=>"agency_id"));
			if(count($indData)>0){
				$indIds=implode_r(",",$indData);
				$sharedIds=isSharedModule("emar-individual");
				if(!empty($sharedIds)){
					$indIds.=','.isSharedModule("emar-individual");
				}
			}
		    else{
				$indIds=isSharedModule("emar-individual");
			}
		}
		if(empty($indIds)){
			$indIds=0;
		}
		if($sWhere){
			$sWhere.=" and mar_status='1' and pos_agent_id IN(".$indIds.")"; 
		}
		else{
			$sWhere.=" where mar_status='1' and pos_agent_id IN(".$indIds.")"; 
		}
		if(!empty($dstarts)){
			$sWhere.=" and DATE(pos_added_date) >='".$dstarts."'";
		}
		if(!empty($dends)){
			$sWhere.=" and DATE(pos_added_date) <='".$dends."'";
		}
		$sQuery = "SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $aColumns))." from _uhs_pos  join _uhs_agency on _uhs_pos.pos_agent_id=_uhs_agency.agency_id  $sWhere $sOrder $sLimit";
 		$qry = $this->dbObj->query($sQuery)->fetchAll();
		$sQuery = "SELECT FOUND_ROWS() as fcnt";
		$aResultFilterTotal =  $this->dbObj->query($sQuery)->fetchAll(); 
		$iFilteredTotal = $aResultFilterTotal[0]['fcnt'];
		$sQuery = "SELECT COUNT(`".$sIndexColumn."`) as cnt FROM $sTable ";
		$rResultTotal = $this->dbObj->query($sQuery)->fetchAll(); 
		$iTotal = $rResultTotal[0]['cnt'];
		$output = array(
 				"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
			);
		$j=0;
		foreach($qry as $row1){
			$modifierData=array();
			if($row1['pos_modified_by']!=0){
				$modifierData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_modified_by']);
			}
			$disClick='href="'.APPLICATION_URL.'/medication-discontinue-mar/'.$row1[$sIndexColumn].'"';
			$editClick='href="'.APPLICATION_URL.'/edit-medication-order-sheet/'.$row1[$sIndexColumn].'"';
			$viewClick='href="'.APPLICATION_URL.'/view-physician-mar-order-sheet/'.$row1[$sIndexColumn].'"';
			if(!isset($healthSession->emarSecurity) && $healthSession->emarSecurity!=1 && ($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers))){
				$disClick='onclick="securityCheck(6,'.$row1[$sIndexColumn].');"';
				$editClick='onclick="securityCheck(4,'.$row1[$sIndexColumn].');"';
				$viewClick='onclick="securityCheck(5,'.$row1[$sIndexColumn].');"';
			}
			$creatorData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_created_by']);
			$permissions=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_id);
			$editLink=$pos_discontinue_reason_val="";$disClass="disabled=disabled";
			if(in_array($this->view->user->agency_user_type,$systemusers)){
				$parentPermission=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_user_agency_id);
				if(($parentPermission=="Both" || $parentPermission=="Edit") && ($permissions=="Both" || $permissions=="Edit")){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-times-circle"></i> Discontinue Medication  </a>';
				}
			}
			else{
				if($permissions=="Both" || $permissions=="Edit"){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-times-circle"></i> Discontinue Medication  </a>';
				}
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$disClass="";
			}
			$row=array();
 			$row[] = $j+1;
			$row[]='<input class="elem_ids checkboxes" '.$disClass.' type="checkbox" name="'.$sTable.'['.$row1[$sIndexColumn].']"  value="'.$row1[$sIndexColumn].'">';
			$row[]=($row1['pos_number']);
  			$row[]=ucwords($row1['pos_patient_fname']." ".$row1['pos_patient_lname']);
			$row[]=$row1['pos_email'];       
			$userType=printUserType($creatorData);
			$row[]=ucwords($creatorData['agency_first_name']." ".$creatorData['agency_last_name'])."<br/>".$userType;  
			$row[]=formatDateTimeNew($row1['pos_added_date']);
			if(!empty($modifierData)){
				$userType=printUserType($modifierData);
				$row[]=ucwords($modifierData['agency_first_name']." ".$modifierData['agency_last_name'])."&nbsp;".$userType;
				$row[]=formatDateTime($row1['pos_updated_date']);
			}     
			else{
				$row[]="-";
				$row[]="-";
			}
			$publishUrl="";
			if($row1['pos_published_status']==0){
				$row[]='<span class="badge badge-danger">No</span>';
				if(checkEmarCreationPermission($row1['pos_agent_id'])){
					$publishUrl='&nbsp; <a class="btn btn-xs btn-default" style="margin-bottom:4px;" href="'.APPLICATION_URL.'/emar/publish/type/pos/pos_id/'.$row1[$sIndexColumn].'"><i class="fa fa-bullseye"></i> Publish </a>';
				}
			}
			else{
				$row[]='<span class="badge badge-success">Yes</span>';
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$editLink='<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$editClick.'><i class="fa fa-edit"></i> Edit </a>';
			}			
			$row[]=$editLink.$publishUrl.'&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$viewClick.'><i class="fa fa-search"></i> View </a>'.$pos_discontinue_reason_val.'';
 			$output['aaData'][] = $row;
			$j++;
		}
		echo json_encode( $output );
		exit();
	}
	
	public function addmarAction(){
		global $healthSession,$systemusers,$pharmacyusers; 
		$this->view->pageHeading="Add Medication Administration Record";
		$agents=$this->SuperModel->GetIndivisuals($this->view->user->agency_id,2);
		if(empty($agents)){
			$healthSession->errorMsg = "No individual assigned to you with edit permission.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		if(count($agents)==1){
			$healthSession->errorMsg = "No more individual exists or subscription of individuals has not been completed.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
 		$form = new Application_Form_Emar();
		$form->posform($this->view->user->agency_id,'','','2');
 		if($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost();
   			if($form->isValid($data)){
				unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
				//------------30.10.18------------------------
				$madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //-----------------------------------------------
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_prescription_routine']=array_values($data['pos_prescription_routine']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				
				$madicationData['feq_day']=array_values($data['feq_day']);
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
                               // $madicationData['primary_physician_option']=array_values($data['option']);
				 
				unset($data['feq_day']);
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);
				
				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_frequency']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_prescription_routine']);
				unset($data['pos_medication_prn']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_prescription_treatment']);
				unset($data['pos_prescription_sideeffect']);
		       //------------29.10.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//----------------------------------------
				//unset($data['option']);
				
				$totalmadication=count($madicationData['pos_medication_name']);
                                
				
				$data['pos_created_by']=$this->view->user->subuser_id; //$this->view->user->agency_id;
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto'])){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_added_date']=date('Y-m-d H:i:s');
				$data['pos_number']=getRandomString(6,'alpha');
				$data['mar_status']=1;
				$get_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and pos_status="1" and mar_status="0"');
				if(empty($get_pos)){
					$data['pos_status']=1;
				}
				$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and tar_status="1" and mar_status="0"');
				if(empty($get_data_pos)){
					$data['tar_status']=1;
				}
                                
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data);
				$insertedId=$s->inserted_id ; 
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
					//------------29.10.18-------------------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//----------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_prescription_routine']=$madicationData['pos_prescription_routine'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_prescription_treatment']="No";
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					//$madicationDatanew['primary_physician_option']=$madicationData['primary_physician_option'][$i];
					$madicationDatanew['medication_pos_id']=$insertedId;
					$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array(
										'umt_medication_id'=>$a->inserted_id,
										'umt_freq_id'=>$isIns->inserted_id,
										'umt_time'=>$timeString,
										'is_umt_prn'=>$isPrn,
										'umt_added'=>date("Y-m-d H:i:s")
								);
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily" || $madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array('umt_medication_id'=>$a->inserted_id,'umt_freq_id'=>$isIns->inserted_id,'umt_time'=>$timeString,'is_umt_prn'=>$isPrn,'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
				}
				
				if((in_array($this->view->user->agency_user_type,$systemusers) || in_array($this->view->user->agency_user_type,$pharmacyusers) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id!=0)) && checkNotifySettings($this->view->user->agency_user_agency_id,"mar")){
					$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>"mar","notification_type_id"=>$insertedId,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
					$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);	
				}
				$healthSession->successMsg="MAR (Medication Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been added successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}else{
				$healthSession->errorMsg="Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
	}
	
	public function editmarAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}
		}
		$this->view->pageHeading="Update Medication Administration Record";
 		$form = new Application_Form_Emar();
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$form->posform($this->view->user->agency_id,$pos_id,'',2);
		if(!empty($po_data['pos_patient_dob']) && $po_data['pos_patient_dob']!="0000-00-00"){
			$po_data['pos_patient_dob']=date('m/d/Y',strtotime($po_data['pos_patient_dob']));
		}
		if(!empty($po_data['pos_admission_date']) && $po_data['pos_admission_date']){
			$po_data['pos_admission_date']=date('m/d/Y',strtotime($po_data['pos_admission_date']));
		}
		if(!empty($po_data['pos_charting_from']) && $po_data['pos_charting_from']!=NULL){
			$po_data['pos_charting_fromto']=date('m/d/Y',strtotime($po_data['pos_charting_from'])).'-'.date('m/d/Y',strtotime($po_data['pos_charting_to']));
		}
		$form->populate($po_data);
		$atimes=array('_uhs_medication_times','umt_medication_id=medication_id','left',array('group_concat(umt_time) as times'));
		// $uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."'",'fetchAll',array('fields'=>array('*'),'group'=>'medication_id'),array(0=>$atimes));
		$uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetchAll',array("fields"=>"*"));

		//------------------------------------23.10.18---------------------------------------------
		foreach ($uhs_pos_medicationdata as $key => $value) {
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace('"', '”', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace("'", '’', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace("'", '’', $value['pos_medication_direction']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace('"', '”', $value['pos_medication_direction']);
			
		}
		//----------------------------------------------------------------------------
		$this->view->uhs_pos_medicationdata=$uhs_pos_medicationdata;
	
 		if($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost();	
			//prd($data);
			//echo "<pre>";
			//print_r($data);
			//die;
   			if($form->isValid($data)){
				unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
			     //------------29.10.18------------------------
			    $madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //----------------------------------------------
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_prescription_routine']=array_values($data['pos_prescription_routine']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_control_medication']=array_values($data['pos_control_medication']);
				$madicationData['pos_no_of_pills']=array_values($data['pos_no_of_pills']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				
				$madicationData['feq_day']=array_values($data['feq_day']);
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				//$madicationData['primary_physician_option']=array_values($data['option']);
				unset($data['feq_day']);
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);
				
				unset($data['pos_control_medication']);
				unset($data['pos_no_of_pills']);


				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_frequency']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_prescription_routine']);
				unset($data['pos_medication_prn']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_prescription_sideeffect']);
				//------------29.10.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//---------------------------------------------
				//unset($data['option']);
				
				$totalmadication=count($madicationData['pos_medication_name']);
                                
//                                for($op=0;$op<$totalmadication;$op++){
//                                    $option = "option".$op;
//                                    unset($data[$option]);
//                                }
		
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto'])){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_updated_date']=date('Y-m-d H:i:s');
				$data['pos_modified_by']=$this->view->user->subuser_id;
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data,'pos_id="'.$pos_id.'"');
				//echo $totalmadication; die;
				
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
					//------------29.10.18------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//-----------------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_medication_reminder']=$remindVal;
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_prescription_routine']=$madicationData['pos_prescription_routine'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_control_medication']=$madicationData['pos_control_medication'][$i];
					$madicationDatanew['pos_no_of_pills']=$madicationData['pos_no_of_pills'][$i];
					//$madicationDatanew['primary_physician_option']=$madicationData['primary_physician_option'][$i];
					if($madicationData['pos_prescription_routine'][$i]=="Yes" || $madicationData['pos_medication_prn'][$i]=="Yes"){
						$madicationDatanew['pos_prescription_treatment']="No";
					}
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					$madicationDatanew['medication_pos_id']=$pos_id;
					if($madicationData['pos_medication_id'][$i]!=""){
						
						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew,'medication_id='.$madicationData['pos_medication_id'][$i]);
						$mid=$madicationData['pos_medication_id'][$i];
					}
					else{
						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
						$mid=$a->inserted_id;
					}
					if($madicationData['pos_medication_id'][$i]!=""){
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_frequencies","umt_freq_medication_id=".$madicationData['pos_medication_id'][$i]);
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_times","umt_medication_id=".$madicationData['pos_medication_id'][$i]);
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array(
										'umt_medication_id'=>$mid,
										'umt_freq_id'=>$isIns->inserted_id,
										'umt_time'=>$timeString,
										'is_umt_prn'=>$isPrn,
										'umt_added'=>date("Y-m-d H:i:s")
								);
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily"){
						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$mid,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
                                        
                    if($madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array(
									'umt_medication_id'=>$mid,
									'umt_freq_id'=>$isIns->inserted_id,
									'umt_time'=>$timeString,
									'is_umt_prn'=>$isPrn,
									'umt_added'=>date("Y-m-d H:i:s")
							);
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
				}
				$healthSession->successMsg=" MAR (Medication Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been updated successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		
		$this->view->form =$form;
		
		$this->render('addmar');
	}
	
	public function discontinuemarAction(){

		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->pageHeading="Discontinue Medication ".ucwords($po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname'])." ";
		$redirect_route="physician_order_sheet";
 		$form = new Application_Form_Emar();
		$avail_data=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetch',array('fields'=>array('medication_id')));
		$this->view->avail_data=$avail_data;
		$form->pos_discontinue($pos_id);
		$form->populate($po_data);
		$users=array("_uhs_agency","agency_id=pos_discontinue_name",'left',array('agency_first_name','agency_last_name'));
		$dis_continue_med=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='1'",'fetchAll',array('fields'=>array('*'),'group'=>'medication_id'),array(0=>$users));
		$this->view->dis_continue_med=$dis_continue_med;
		if($this->getRequest()->isPost()) {
			$posted_data = $this->getRequest()->getPost();	
   			if($form->isValid($posted_data)){
				$data=$form->getValues();
				$med_id=$data['medication_id'];
				unset($data['medication_id']);
				$data['pos_discontinue_name']=$this->view->user->agency_id;
				$data['pos_discontinue_status']=1;
				$data['pos_discontinue_datetime']=date("Y-m-d H:i:s");
				$po_data=$this->SuperModel->Super_Insert("_uhs_pos_medication",$data,"medication_id='".$med_id."'");
				$healthSession->successMsg="Discontinue for the medication is done successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
		 $this->render('discontinue');
	}
	
	public function viewmarAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily'and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4);
		
		$this->view->med_po_data=$med_po_data;
		
		$med=array('_uhs_pos_medication','umt_medication_id=medication_id','left',array());
		$pos=array('_uhs_pos','pos_id=medication_pos_id','left',array());
		
		$atimes=$this->SuperModel->Super_Get("_uhs_medication_times","pos_id='".$pos_id."'",'fetchall',array('fields'=>array((new Zend_Db_Expr("DISTINCT CONCAT(MONTHNAME(umt_added), '/', YEAR(umt_added)) AS Month" ))),'order'=>'umt_added DESC'),array(0=>$med,1=>$pos));
	
		$this->view->time_arr=$atimes;
		$this->view->pageHeading="Medication Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
	}
	
	public function viewmarnewAction(){

		global $healthSession,$systemusers; 
        $pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$this->view->current_action=$this->view->current_action;
        $po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		
		$this->view->indi_parent_data=$indi_parent_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$filter = '';
		if(isset($_GET['frequency']) && !empty($_GET['frequency'])){
			$frequency = $_GET['frequency'];

		$med_po_data = $this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='".$frequency."'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		} else {
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		$med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
        $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		$med_po_data = array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		}
		$amend = array();
		$amendumct = array();
		$ammend2 = [];
		foreach($med_po_data as $value){
            $result_amend = $this->SuperModel->Super_get("_uhs_pos_amend","medication_id = ".$value['medication_id'], 'fetchAll');
           if(count($result_amend[0])>0){
            	foreach($result_amend as $am_res){
            		   $amend_user = $this->SuperModel->Super_get("_uhs_agency","agency_id = ".$am_res['user_id']);
            		    $am_res['created_by'] = $amend_user['agency_first_name']." ".$amend_user['agency_last_name'];
                            $amendumct[$value['medication_id']][$am_res['date']][$am_res['umc_time']] = $am_res;
                            $amend[$value['medication_id']][$am_res['date']] = $am_res;
            	}
                
        	}
            $ammend2[] = $result_amend;

            
        }
      //echo "<pre>";print_r($med_po_data);die;
      //echo "<pre>";print_r($amend);die;
		
		//prd($amend);
		$med=array('_uhs_pos_medication','umt_medication_id=medication_id','left',array());
		$pos=array('_uhs_pos','pos_id=medication_pos_id','left',array());
		$atimes=$this->SuperModel->Super_Get("_uhs_medication_times","pos_id='".$pos_id."'",'fetchall',array('fields'=>array((new Zend_Db_Expr("DISTINCT CONCAT(MONTHNAME(umt_added), '/', YEAR(umt_added)) AS Month" ))),'order'=>'umt_added DESC'),array(0=>$med,1=>$pos));
		$this->view->time_arr=$atimes;
		$this->view->pageHeading="Medication Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
                $indid=$po_data['pos_agent_id'];
                $formid=500;
                if($this->view->user->agency_user_type == "Agency") {
                    $agencyid=$this->view->user->agency_id;
                    $amendview_data = $this->SuperModel->Super_Get("_uhs_amend_view", "am_agency_id='".$agencyid."' AND am_indid='".$indid."' AND am_form_id='".$formid."' ", "fetch");
                    if(!empty($amendview_data)){
                        $this->view->am_status = $amendview_data['am_status'];
                        $this->view->am_id = $amendview_data['am_id'];
                    }else {
                        $this->view->am_status = 0;
                        $this->view->am_id = 0;
                     }
               }elseif(in_array($this->view->user->agency_user_type,$systemusers)) {
                    $agencyid=$this->view->user->agency_user_agency_id;
                    $amendview_data = $this->SuperModel->Super_Get("_uhs_amend_view", "am_agency_id='".$agencyid."' AND am_indid='".$indid."' AND am_form_id='".$formid."' ", "fetch");
                    if(!empty($amendview_data)){
                       $this->view->am_status = $amendview_data['am_status'];
                    } else {
                     $this->view->am_status = 0;
                    }
                }
                $this->view->indid = $indid;
                $this->view->formid = $formid;
                $this->view->agency_user_type = $this->view->user->agency_user_type;
                $form_bp = new Application_Form_Individual(); 
                $form_bp->emarbloodPressureElements('');
                $form_bg = new Application_Form_Individual(); 
                $form_bg->emarbloodGlucoseElements('');
                $form_bw = new Application_Form_CustomForms();
                $form_bw->emarbowelmovement('');
                $this->view->form_bp=$form_bp;
                $this->view->form_bg=$form_bg;
                $this->view->form_bw=$form_bw;
                $this->view->med_po_data = $med_po_data;
				$this->view->amend_data = $amend;
				$this->view->amendumct_data = $amendumct;
  				              
        }

    //------------------------modified at 30.10.18-----------------------------------
    private function getType($type,$id){
		global $healthSession;
		$mtypeArr=array();
		switch($type){
			case "EmarBloodGlucose":
				$mtypeArr=array("murl"=>"blood-glucose","table"=>"_uhs_blood_glucose","where"=>"gid=".$id,"route"=>"view_mar_med_notes","modelHeading"=>"Blood Glucose","msgType"=>"Blood glucose","created_by"=>"bg_created_by","ind_id"=>"bg_ind_id","added_on"=>"bg_added_on","modified_by"=>"bg_modified_by","modified_on"=>"bg_modified_on","fields"=>"blood_glucose as Blood Glucose,bg_reading_date as Reading Date,bg_reading_type as Reading Type,bg_reading_other_type as Other Reading Type,bg_action_taken as Action Taken,bg_other_action_taken as Other Action Taken,bg_reading_source as Reading Source,bg_reading_other_source as Reading Other Source,bg_added_on as AddedOn","jointable"=>"_uhs_agency","joincondition"=>"agency_id=bg_created_by","joinfields"=>"CONCAT(agency_first_name,' ',agency_last_name) as CreatedBy","readingDate"=>"bg_reading_date");
				break;
				
			case "EmarBloodPressure":
				$mtypeArr=array("murl"=>"blood-pressure","table"=>"_uhs_blood_pressure","where"=>"bid=".$id,"route"=>"view_mar_med_notes","modelHeading"=>"Blood Pressure","msgType"=>"Blood pressure","created_by"=>"b_created_by","ind_id"=>"b_ind_id","added_on"=>"bcreated_on","modified_by"=>"b_modified_by","modified_on"=>"b_modified_on","fields"=>"heart_rate as Heart Rate,systolic as Systolic,diastolic as Diastolic,reading_source as Reading Source,reading_other_source as Reading Other Source,reading_date as Reading Date,reading_time as Reading Time,bcreated_on as AddedOn","jointable"=>"_uhs_agency","joincondition"=>"agency_id=b_created_by","joinfields"=>"CONCAT(agency_first_name,' ',agency_last_name) as CreatedBy","readingDate"=>"reading_date");
				break;

			
		}
		return $mtypeArr;
	}

	public function getnewformsAction(){

		global $healthSession;
		$id=$_REQUEST['id'];
		$indid=$_REQUEST['indid'];
		$form= new Application_Form_Individual();
		$mtypeArr=$this->getType($_REQUEST['mtype'],$id);
		$data=array(); $document=$height_feets=$height_inches="";
		$form->emarmodules($_REQUEST['mtype'],$indid,$id);
		if(!empty($id)){
			$data=$this->SuperModel->Super_Get($mtypeArr['table'],$mtypeArr['where']);
			if(isset($mtypeArr['readingDate']) && !empty($mtypeArr['readingDate'])){
				 if(isset($data[$mtypeArr['readingDate']]) && !empty($data[$mtypeArr['readingDate']])){
					$data[$mtypeArr['readingDate']]=date("m/d/Y",strtotime($data[$mtypeArr['readingDate']]));
				 }
			 }
			 if(isset($mtypeArr['imNext']) && !empty($mtypeArr['imNext'])){
				 if(isset($data[$mtypeArr['imNext']]) && !empty($data[$mtypeArr['imNext']])){
					$data[$mtypeArr['imNext']]=date("m/d/Y",strtotime($data[$mtypeArr['imNext']]));
				 }
			 }
			if($_REQUEST['mtype']=="MedicalHistory"){
			 $document="<p id='insertedvideo'><a target='_blank' href='".HTTP_MH_PATH."/".$data['history_document']."'>".formatDocumentName($data['history_document'])."</a></p>";
			}
			if($_REQUEST['mtype']=="LabTestResult"){
			 $document="<p id='insertedvideo'><a target='_blank' href='".HTTP_LAB_PATH."/".$data['lab_test_report']."'>".formatDocumentName($data['lab_test_report'])."</a></p>";
			}
			if($_REQUEST['mtype']=="Weight"){
				$hArray=explode(".",$data['height']);
				$height_feets=$hArray[0];
				$height_inches=$hArray[1];
				//$data['measured_date']=date("m/d/Y",strtotime($data['measured_date']));
			}
			$form->populate($data);
		}
		echo $mtypeArr['modelHeading']."~~".$form."~~".$document."~~".$height_feets."~~".$height_inches;
		exit();
	}
    
    public function addemarentryAction(){
		global $healthSession,$systemusers; 
		$id=$this->_getParam('id');
		$type=$this->_getParam('type');
		$indid=$this->_getParam('indid');

		$this->view->pageHeading="Emergency Contact";
 		$form=new Application_Form_Individual();
		$form->modules($type,$id);
		$table=$where=""; $router="";
		$mtypeArr=$this->getType($type,$id);
		if(!empty($id)){
			$data=$this->SuperModel->Super_Get($mtypeArr['table'],$mtypeArr['where']);
			if($type=="Weight"){
				$hArray=explode(".",$data['height']);
				$data['height_feets']=$hArray[0];
				$data['height_inches']=$hArray[1];
			}
			$form->populate($data);
		}
 		if($this->getRequest()->isPost()){
			$posted_data = $this->getRequest()->getPost();
   			if($form->isValid($posted_data)){
				 unset($posted_data['bttnsubmit']);
				 unset($posted_data['MAX_FILE_SIZE']);
				 if(isset($mtypeArr['readingDate']) && !empty($mtypeArr['readingDate'])){
					 if(isset($posted_data[$mtypeArr['readingDate']]) && !empty($posted_data[$mtypeArr['readingDate']])){
						$posted_data[$mtypeArr['readingDate']]=date("Y-m-d",strtotime($posted_data[$mtypeArr['readingDate']]));
					 }
				 }
				 if(isset($mtypeArr['imNext']) && !empty($mtypeArr['imNext'])){
					 if(isset($posted_data[$mtypeArr['imNext']]) && !empty($posted_data[$mtypeArr['imNext']])){
						$posted_data[$mtypeArr['imNext']]=date("Y-m-d",strtotime($posted_data[$mtypeArr['imNext']]));
					 }
				 }
				 if($type=="MedicalHistory" && !empty($posted_data['history_document'])){
					if(!empty($id)){
						$hData=$this->SuperModel->Super_Get($mtypeArr['table'],$mtypeArr['where']);
						if(!empty($hData['history_document']) && file_exists(MH_PATH.'/'.$hData['history_document']) && $hData['history_document']!=$posted_data['history_document']){
							unlink(MH_PATH.'/'.$hData['history_document']);
						}
					}
				 }
				 if($type=="LabTestResult" && !empty($posted_data['lab_test_report'])){
					if(!empty($id)){
						$labData=$this->SuperModel->Super_Get($mtypeArr['table'],$mtypeArr['where']);
						if(!empty($labData['lab_test_report']) && file_exists(LAB_PATH.'/'.$labData['lab_test_report']) && $labData['lab_test_report']!=$posted_data['lab_test_report']){
							unlink(LAB_PATH.'/'.$labData['lab_test_report']);
						}
					}
				 }
				 if($type=="Weight"){
				 	$posted_data['height']=floatval($posted_data['height_feets'].".".$posted_data['height_inches']);
					unset($posted_data['height_feets']);
					unset($posted_data['height_inches']);
				 }
				 $posted_data[$mtypeArr['ind_id']]=$indid;
				 if(empty($id)){
					 $posted_data[$mtypeArr['created_by']]=$this->view->user->subuser_id; 
					 $posted_data[$mtypeArr['added_on']]=date('Y-m-d H:i:s');
					 // prd($posted_data);
					 // die;
					 $isInserted = $this->SuperModel->Super_Insert($mtypeArr['table'],$posted_data);
					 if($isInserted->success){
					 	
						$posted_data['mtype']=$type;
						$posted_data['indid']=$indid;
						$posted_data['router']=$mtypeArr['murl'];

						if($type=="EmarBloodGlucose"|| $type=="EmarBloodGlucose"){
							$result=array();
							switch($type){
								case "EmarBloodGlucose":
									$result=calculateProgressBar($type,$posted_data['blood_glucose'],$posted_data['bg_reading_type']);
									break;
								case "EmarBloodPressure":
									$result=calculateProgressBar($type,$posted_data['systolic'],$posted_data['diastolic']);
									break;
								
							}
							if(count($result)>0 && $result['color']=="red-bar"){
								$indData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$indid);
								if(in_array($this->view->user->agency_user_type,$systemusers) && checkNotifySettings($this->view->user->agency_user_agency_id,$type."-alert")){
									$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>$type."-alert","notification_type_id"=>$isInserted->inserted_id,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
									$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);
								}
							}
						}						
						if(checkNotifySettings($this->view->user->agency_user_agency_id,$type) && in_array($this->view->user->agency_user_type,$systemusers)){
							$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>$type,"notification_type_id"=>$isInserted->inserted_id,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
							$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);
							//$isSend=$this->modelEmail->sendEmail("individual_vital_entry_notification",$posted_data);
							$healthSession->successMsg= $mtypeArr['msgType']." has been added successfully";
						}
					 }
					 else{
						$healthSession->errorMsg = $isInserted->message;
					 }
				 }
				 else{
					$posted_data[$mtypeArr['modified_by']]=$this->view->user->subuser_id; 
					$posted_data[$mtypeArr['modified_on']]=date('Y-m-d H:i:s');
					$isInserted = $this->SuperModel->Super_Insert($mtypeArr['table'],$posted_data,$mtypeArr['where']);
					if($isInserted->success){
						$healthSession->successMsg  = $mtypeArr['msgType']." has been updated successfully";
				    }
					else{
						$healthSession->errorMsg  = $isInserted->message;
					}
				 }
   			}else{
				$healthSession->errorMsg = " Please check information again";
 			}
		}

		//$this->_helper->getHelper("Redirector")->gotoRoute(array(),$mtypeArr['route']);
		$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
	}
    //------------------------------------------------------------------------------------
	public function addamendAction(){
	    global $healthSession;
            // prd($_POST['postData']);
            foreach($_POST['postData'] as $data){
            $pos_id="";
            $current_action="";
            if(!empty($data['data_pos_id'])){
                $pos_id=$data['data_pos_id'];
                unset($data['data_pos_id']);
            }
            if(!empty($data['data_current_action'])){
                $current_action=$data['data_current_action'];
                unset($data['data_current_action']);
            }
            
            
            $data['user_id'] = $_SESSION['UHS_AUTH']['storage']->agency_id;
	    $data['create_date'] = date("Y-m-d H:i");
            //prd($data);
	    $umc_time = $data['umc_time'];
	    $umc_time = date("H:i:s", strtotime($umc_time));
	    $data['umc_time'] = $umc_time;

	   //echo"<pre>";print_r($data);die();
            
            
	    $isIns = $this->SuperModel->Super_Insert('_uhs_pos_amend', $data);
	   }
            if($isIns->success){
                if(!empty($current_action)){
                    if($current_action == 'viewmarchartinghistory'){
                       // $this->_helper->getHelper("Redirector")->gotoRoute(array('pos_id'=>$pos_id,'archid'=>0),"view_mar_chart_history");
                    } elseif($current_action == 'viewmarnew'){
                        // $this->_helper->getHelper("Redirector")->gotoRoute(array('pos_id'=>$pos_id),"view_physician_mar_order_sheet");
                    }
                    }else{
                       // $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
                    }
                }
            exit();
	   // print_r($a);die;
	}

	public function editamendAction(){ //echo "<pre>"; print_r($_POST);die("hello");
	    global $healthSession;
	    $data['user_id'] = $_POST['user_id'];
	    $data['outcome_comments'] = "1";
	    $id = $_POST['amend_id'];
	    $this->SuperModel->edit_amend($id, $data);
	    $outcome_data['amend_id'] = $id;
	    $outcome_data['comments'] = $_POST['description'];
	    $outcome_data['created_by'] = $this->view->user->agency_id;
	    $this->SuperModel->Super_Insert('_uhs_pos_amend_outcome', $outcome_data);
	    $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
	}

	public function viewamendAction(){
		global $healthSession;
	    $data = $_POST;
	    $result = $this->SuperModel->Super_get('_uhs_pos_amend','amend_id='.$_POST['amend_id'] ,'fetch');
	    echo json_encode($result);die;
	}

	public function viewoutcomeAction(){
	    $result = $this->SuperModel->Super_get('_uhs_pos_amend_outcome','amend_id='.$_POST['amend_id'] ,'fetchAll');
	    $output="";
	    if(count($result) > 0){
	    	foreach ($result as $key => $value) {
	    		$temp = $key+1;
	    		$output .= "<tr><td> $temp </td>";
	    		$output .= "<td>".$value['comments']."</td>";
	    		$output .= "<td><small>".date('F m, Y h:i a',strtotime($value['created_at']))."</small></td> </tr>";
	    	}
	    }
	    // $final['status'] = "true";
	    // $final['output'] = $output;
	    echo /*json_encode*/($output);die;
	}
	
	public function viewmarreportAction(){
		
		
		ini_set('max_execution_time', 1000);
		ini_set('memory_limit', '512M');
		global $healthSession;
		$this->view->pageHeading="MAR Report";
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$month=$this->_getParam('month'); 
		$this->view->month=$month;
		$year=$this->_getParam('year'); 
		$this->view->year=$year;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$this->view->med_po_data=$med_po_data;
		$med=array('_uhs_pos_medication','umt_medication_id=medication_id','left',array());
		$pos=array('_uhs_pos','pos_id=medication_pos_id','left',array());
		

		$atimes=$this->SuperModel->Super_Get("_uhs_medication_times","pos_id='".$pos_id."'",'fetchall',array('fields'=>array((new Zend_Db_Expr("DISTINCT CONCAT(MONTHNAME(umt_added), '/', YEAR(umt_added)) AS Month" ))),'order'=>'umt_added DESC'),array(0=>$med,1=>$pos));
		$this->view->time_arr=$atimes;
		$this->view->pageHeading="Medication Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
		$html = $this->view->render('emar/viewmarreport.phtml');
		require_once(ROOT_PATH.'/private/mpdf/mpdf.php');
		$mpdf=new mPDF('utf-8', 'A2');
		$stylesheet = file_get_contents(APPLICATION_URL.'/public/plugins/bootstrap/css/bootstrap.css');
		$stylesheet1 = file_get_contents(APPLICATION_URL.'/public/front_css/style_custom.css');
		$mpdf->SetTitle($this->view->site_configs['site_name']." | ".ucwords($indi_data['agency_first_name']." ".$indi_data['agency_last_name'])." | View MAR");
		$mpdf->WriteHTML($stylesheet,1);
		$mpdf->WriteHTML($stylesheet1,1);
		$mpdf->WriteHTML($html,2);
		$mpdf->Output();
		exit();
		
	}
	
	/* TAR Mgmt. */
	public function tarAction(){
		global $healthSession;
		$this->view->pageHeading="Treatment Administration Records";
		$icode=$this->_getParam("icode");
		$this->view->icode=$icode;
	}
	
	public function gettardataAction(){
		global $healthSession,$systemusers,$pharmacyusers;
 		$this->dbObj = Zend_Registry::get('db');
		$icode=$this->_getParam('icode');
		$pindData=array();
		if($icode!=""){
			$pindData=$this->SuperModel->Super_Get("_uhs_agency","agency_code='".$icode."'","fetch");
		}
		$aColumns = array('pos_id','pos_number','pos_patient_fname','pos_patient_lname','pos_email','pos_published_status','pos_status','tar_status','pos_created_by','pos_added_date','pos_updated_date','pos_agent_id','pos_modified_by');
		$sIndexColumn = 'pos_id';
		$sTable = '_uhs_pos';
		$sLimit = "";
		if(isset($_REQUEST['subuser']) && !empty($_REQUEST['subuser'])){
			$subuser=$_REQUEST['subuser'];
		}
		if(isset($_REQUEST['dstarts']) && !empty($_REQUEST['dstarts'])){
			$dstarts=$_REQUEST['dstarts'];
		}
		if(isset($_REQUEST['dends']) && !empty($_REQUEST['dends'])){
			$dends=$_REQUEST['dends'];
		}
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' ){
			$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".intval( $_GET['iDisplayLength'] );
		}
		$sOrder = "";
		if ( isset( $_GET['iSortCol_0'] ) ){
			$sOrder = "ORDER BY  ";
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder .= "".$aColumns[ intval( $_GET['iSortCol_'.$i] ) ]." ".
						($_GET['sSortDir_'.$i]==='asc' ? 'asc' : 'desc') .", ";
				}
			}
			$sOrder = substr_replace( $sOrder, "", -2 );
			if ( $sOrder == "ORDER BY" ){
				$sOrder = "";
			}
		}
		$sWhere = "";
		if ( isset($_GET['sSearch']) and $_GET['sSearch'] != "" ){
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET["sSearch"]."%' OR "; // NEW CODE
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		for ( $i=0 ; $i<count($aColumns) ; $i++ ){
			if ( isset($_GET['bSearchable_'.$i]) and $_GET['bSearchable_'.$i] == "true" and $_GET['sSearch_'.$i] != '' ){
				if ( $sWhere == "" ){
					$sWhere = "WHERE ";
				}
				else{
					$sWhere .= " AND ";
				}
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch_'.$i]."%' ";
			}
		}
		$indIds=0;
		if($icode!="" && !empty($pindData)){
			$indIds=$pindData['agency_id'];
		}
		else{
			$indData=$this->SuperModel->Super_Get("_uhs_agency","agency_user_agency_id=".$this->view->user->agency_id." and agency_user_type='Individual'","fetchAll",array("fields"=>"agency_id"));
			$indIds=0;
			if(count($indData)>0){
				$indIds=implode_r(",",$indData);
				$sharedIds=isSharedModule("emar-individual");
				if(!empty($sharedIds)){
					$indIds.=','.isSharedModule("emar-individual");
				}
			}
			else{
				$indIds=isSharedModule("emar-individual");
			}
		}
		if(empty($indIds)){
			$indIds=0;
		}
		if($sWhere){
			$sWhere.=" and tar_status='1' and pos_agent_id IN(".$indIds.")"; 
		}
		else{
			$sWhere.=" where tar_status='1' and pos_agent_id IN(".$indIds.")"; 
		}
		if(!empty($dstarts)){
			$sWhere.=" and DATE(pos_added_date) >='".$dstarts."'";
		}
		if(!empty($dends)){
			$sWhere.=" and DATE(pos_added_date) <='".$dends."'";
		}
		$sQuery = "SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $aColumns))." from _uhs_pos  join _uhs_agency on _uhs_pos.pos_agent_id=_uhs_agency.agency_id  $sWhere $sOrder $sLimit";
 		$qry = $this->dbObj->query($sQuery)->fetchAll();
		$sQuery = "SELECT FOUND_ROWS() as fcnt";
		$aResultFilterTotal =  $this->dbObj->query($sQuery)->fetchAll(); 
		$iFilteredTotal = $aResultFilterTotal[0]['fcnt'];
		$sQuery = "SELECT COUNT(`".$sIndexColumn."`) as cnt FROM $sTable ";
		$rResultTotal = $this->dbObj->query($sQuery)->fetchAll(); 
		$iTotal = $rResultTotal[0]['cnt'];
		$output = array(
 				"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
			);
		$j=0;
		foreach($qry as $row1){
			$modifierData=array();
			if($row1['pos_modified_by']!=0){
				$modifierData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_modified_by']);
			}
			$disClick='href="'.APPLICATION_URL.'/medication-discontinue-tar/'.$row1[$sIndexColumn].'"';
			$editClick='href="'.APPLICATION_URL.'/edit-treatment-order-sheet/'.$row1[$sIndexColumn].'"';
			$viewClick='href="'.APPLICATION_URL.'/view-physician-tar-order-sheet/'.$row1[$sIndexColumn].'"';
			if(!isset($healthSession->emarSecurity) && $healthSession->emarSecurity!=1 && ($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers))){
				$disClick='onclick="securityCheck(9,'.$row1[$sIndexColumn].');"';
				$editClick='onclick="securityCheck(7,'.$row1[$sIndexColumn].');"';
				$viewClick='onclick="securityCheck(8,'.$row1[$sIndexColumn].');"';
			}
			$creatorData=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['pos_created_by']);
			$permissions=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_id);
			$editLink=$pos_discontinue_reason_val="";$disClass="disabled=disabled";
			if(in_array($this->view->user->agency_user_type,$systemusers)){
				$parentPermission=checkPermissions("emar",$row1['pos_agent_id'],$this->view->user->agency_user_agency_id);
				if(($parentPermission=="Both" || $parentPermission=="Edit") && ($permissions=="Both" || $permissions=="Edit")){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-eye"></i> Discontinue Medication  </a>';
				}
			}
			else{
				if($permissions=="Both" || $permissions=="Edit"){
					$pos_discontinue_reason_val='&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$disClick.'><i class="fa fa-eye"></i> Discontinue Medication  </a>';
				}
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$disClass="";
			}
			$row=array();
 			$row[] = $j+1;
			$row[]='<input class="elem_ids checkboxes" '.$disClass.' type="checkbox" name="'.$sTable.'['.$row1[$sIndexColumn].']"  value="'.$row1[$sIndexColumn].'">';
			$row[]=($row1['pos_number']);
  			$row[]=ucwords($row1['pos_patient_fname']." ".$row1['pos_patient_lname']);
			$row[]=$row1['pos_email'];   
			$userType=printUserType($creatorData);
			$row[]=ucwords($creatorData['agency_first_name']." ".$creatorData['agency_last_name'])."<br/>".$userType; 
			$row[]=formatDateTimeNew($row1['pos_added_date']);
			if(!empty($modifierData)){
				$userType=printUserType($modifierData);
				$row[]=ucwords($modifierData['agency_first_name']." ".$modifierData['agency_last_name'])."&nbsp;".$userType;
				$row[]=formatDateTime($row1['pos_updated_date']);
			}     
			else{
				$row[]="-";
				$row[]="-";
			}
			$publishUrl="";
			if($row1['pos_published_status']==0){
				$row[]='<span class="badge badge-danger">No</span>';
				if(checkEmarCreationPermission($row1['pos_agent_id'])){
					$publishUrl='&nbsp; <a class="btn btn-xs btn-default" style="margin-bottom:4px;" href="'.APPLICATION_URL.'/emar/publish/type/pos/pos_id/'.$row1[$sIndexColumn].'"><i class="fa fa-bullseye"></i> Publish </a>';
				}
			}
			else{
				$row[]='<span class="badge badge-success">Yes</span>';
			}
			if(checkEmarCreationPermission($row1['pos_agent_id'])){
				$editLink='<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$editClick.'><i class="fa fa-edit"></i> Edit </a>';
			}	
			$row[]=$editLink.$publishUrl.'&nbsp;<a class="btn btn-xs btn-default" style="margin-bottom:4px;" '.$viewClick.'><i class="fa fa-search"></i> View </a>'.$pos_discontinue_reason_val.'';
			
			$output['aaData'][] = $row;
			$j++;
		}
		echo json_encode($output);
		exit();
	}
	
	public function addtarAction(){
		global $healthSession,$systemusers,$pharmacyusers; 
		$this->view->pageHeading="Add Treatment Administration Record";
		$agents=$this->SuperModel->GetIndivisuals($this->view->user->agency_id,3);
		if(empty($agents)){
			$healthSession->errorMsg = "No individual assigned to you with edit permission.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		if(count($agents)==1){
			$healthSession->errorMsg = "No more individual exists or subscription of individuals has not been completed.";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
 		$form = new Application_Form_Emar();
		$form->posform($this->view->user->agency_id,'','',3);
 		if($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost();	
   			if($form->isValid($data)){
				unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
				//------------12.11.18------------------------
				$madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //-----------------------------------------------
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_prescription_treatment']=array_values($data['pos_prescription_treatment']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				
				if(isset($data['feq_day'])){
					$madicationData['feq_day']=array_values($data['feq_day']);
					unset($data['feq_day']);
				}
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
				
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);
				
				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_medication_prn']);
				unset($data['pos_prescription_treatment']);
				unset($data['pos_prescription_routine']);
				unset($data['pos_prescription_sideeffect']);
				//------------12.11.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//----------------------------------------
				
				$totalmadication=count($madicationData['pos_medication_name']);
				$data['pos_created_by']=$this->view->user->subuser_id; //$this->view->user->agency_id;
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto'])){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_added_date']=date('Y-m-d H:i:s');
				$data['pos_number']=getRandomString(6,'alpha');
				$data['tar_status']=1;
				$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and pos_status="1" and tar_status="0"');
				if(empty($get_pos)){
					$data['pos_status']=1;
				}
				$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$data['pos_agent_id'].'" and mar_status="1" and tar_status="0"');
				if(empty($get_data_pos)){
					$data['mar_status']=1;
				}
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data);
				$insertedId=$s->inserted_id; 
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
						//------------12.11.18-------------------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//----------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_prescription_treatment']=$madicationData['pos_prescription_treatment'][$i];
					$madicationDatanew['pos_prescription_routine']="No";
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					
					$madicationDatanew['medication_pos_id']=$insertedId;
					$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array('umt_medication_id'=>$a->inserted_id,'umt_freq_id'=>$isIns->inserted_id,'umt_time'=>$timeString,'is_umt_prn'=>$isPrn,'umt_added'=>date("Y-m-d H:i:s"));
								
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily" || $madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$a->inserted_id,"umt_freq_pos_id"=>$insertedId,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array('umt_medication_id'=>$a->inserted_id,'umt_freq_id'=>$isIns->inserted_id,'umt_time'=>$timeString,'is_umt_prn'=>$isPrn,'umt_added'=>date("Y-m-d H:i:s"));
							
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
				}
				if((in_array($this->view->user->agency_user_type,$systemusers) || in_array($this->view->user->agency_user_type,$pharmacyusers) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id!=0)) && checkNotifySettings($this->view->user->agency_user_agency_id,"tar")){
					$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>"tar","notification_type_id"=>$insertedId,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
					$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);	
				}
				$healthSession->successMsg="TAR (Treatment Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been added successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}else{
				$healthSession->errorMsg="Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
	}
	
	public function edittarAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}
		}
		$this->view->pageHeading="Update Treatment Administration Record";
 		$form = new Application_Form_Emar();
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$form->posform($this->view->user->agency_id,$pos_id,'','3');
		if(!empty($po_data['pos_patient_dob']) && $po_data['pos_patient_dob']!="0000-00-00"){
			$po_data['pos_patient_dob']=date('m/d/Y',strtotime($po_data['pos_patient_dob']));
		}
		if(!empty($po_data['pos_admission_date']) && $po_data['pos_admission_date']!="0000-00-00"){
			$po_data['pos_admission_date']=date('m/d/Y',strtotime($po_data['pos_admission_date']));
		}
		if(!empty($po_data['pos_charting_from']) && $po_data['pos_charting_from']!=NULL){
			$po_data['pos_charting_fromto']=date('m/d/Y',strtotime($po_data['pos_charting_from'])).'-'.date('m/d/Y',strtotime($po_data['pos_charting_to']));
		}
		$form->populate($po_data);
		$atimes=array('_uhs_medication_times','umt_medication_id=medication_id','left',array('group_concat(umt_time) as times'));
		// $uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."'",'fetchAll',array('fields'=>array('*'),'group'=>'medication_id'),array(0=>$atimes));
		$uhs_pos_medicationdata=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetchAll',array("fields"=>"*"));

		//------------------------------------23.10.18---------------------------------------------
		foreach ($uhs_pos_medicationdata as $key => $value) {
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace('"', '”', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_brand'] = str_replace("'", '’', $value['pos_medication_brand']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace("'", '’', $value['pos_medication_direction']);
			$uhs_pos_medicationdata[$key]['pos_medication_direction'] = str_replace('"', '”', $value['pos_medication_direction']);
			
		}
		//----------------------------------------------------------------------------

		$this->view->uhs_pos_medicationdata=$uhs_pos_medicationdata;
 		if($this->getRequest()->isPost()) {
			$data = $this->getRequest()->getPost();
   			if($form->isValid($data)){
				unset($data['bttnsubmit']);
				$madicationData['pos_medication_brand']=array_values($data['pos_medication_brand']);
				$madicationData['pos_medication_id']=array_values($data['pos_medication_id']);
				$madicationData['pos_medication_name']=array_values($data['pos_medication_name']);
				$madicationData['pos_medication_direction']=array_values($data['pos_medication_direction']);
				$madicationData['pos_medication_rx_number']=array_values($data['pos_medication_rx_number']);
				$madicationData['pos_medication_odate']=array_values($data['pos_medication_odate']);
				$madicationData['pos_medication_frequency_type']=array_values($data['pos_medication_frequency_type']);
				$madicationData['pos_medication_reminder']=array_values($data['pos_medication_reminder']);
				  //------------12.11.18------------------------
			    $madicationData['pos_add_blood_pressure']=array_values($data['pos_add_blood_pressure']);
			    $madicationData['pos_add_blood_sugar']=array_values($data['pos_add_blood_sugar']);
			    $madicationData['pos_add_bowel_movement']=array_values($data['pos_add_bowel_movement']);
			    //----------------------------------------------
				$madicationData['pos_pphysician_fname']=array_values($data['pos_pphysician_fname']);
				$madicationData['pos_pphysician_lname']=array_values($data['pos_pphysician_lname']);
				$madicationData['pos_pphysician_address']=array_values($data['pos_pphysician_address']);
				$madicationData['pos_pphysician_phone']=array_values($data['pos_pphysician_phone']);
				$madicationData['pos_pphysician_email']=array_values($data['pos_pphysician_email']);
				$madicationData['pos_physician_refilno']=array_values($data['pos_physician_refilno']);
				$madicationData['pos_medication_prn']=array_values($data['pos_medication_prn']);
				$madicationData['pos_control_medication']=array_values($data['pos_control_medication']);
				$madicationData['pos_no_of_pills']=array_values($data['pos_no_of_pills']);
				$madicationData['pos_prescription_treatment']=array_values($data['pos_prescription_treatment']);
				$madicationData['pos_prescription_sideeffect']=array_values($data['pos_prescription_sideeffect']);
				$madicationData['feq_day']=array_values($data['feq_day']);
				$madicationData['freq_times']=array_values($data['freq_times']);
				$madicationData['pos_medication_atime']=array_values($data['pos_medication_atime']);
				
				unset($data['feq_day']);
				unset($data['freq_times']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_brand']);
				unset($data['pos_medication_id']);
				unset($data['pos_medication_name']);
				unset($data['pos_medication_direction']);
				unset($data['pos_medication_rx_number']);
				unset($data['pos_medication_odate']);
				unset($data['pos_medication_frequency']);
				unset($data['pos_medication_frequency_type']);
				unset($data['pos_medication_atime']);
				unset($data['pos_medication_reminder']);
				unset($data['pos_pphysician_fname']);
				unset($data['pos_pphysician_lname']);
				unset($data['pos_pphysician_address']);
				unset($data['pos_pphysician_phone']);
				unset($data['pos_pphysician_email']);
				unset($data['pos_physician_refilno']);
				unset($data['pos_medication_prn']);
				unset($data['pos_prescription_treatment']);
				unset($data['pos_prescription_sideeffect']);
				unset($data['pos_control_medication']);
				unset($data['pos_no_of_pills']);
				//------------12.11.18------------------------
				unset($data['pos_add_blood_pressure']);
				unset($data['pos_add_blood_sugar']);
				unset($data['pos_add_bowel_movement']);
				//---------------------------------------------
				
				$totalmadication=count($madicationData['pos_medication_name']);
				
				if($data['pos_patient_dob']!='' && $data['pos_patient_dob']!=NULL && $data['pos_patient_dob']!="0000-00-00"){
					$data['pos_patient_dob']=date('Y-m-d',strtotime($data['pos_patient_dob']));
				}
				if($data['pos_admission_date']!='' && $data['pos_admission_date']!=NULL){
					$data['pos_admission_date']=date('Y-m-d',strtotime($data['pos_admission_date']));
				}
				if(!empty($data['pos_charting_fromto']) && $data['pos_charting_fromto']!=NULL){
					$fromto=explode("-",$data['pos_charting_fromto']);
					$data['pos_charting_from']=date('Y-m-d',strtotime($fromto[0]));
					$data['pos_charting_to']=date('Y-m-d',strtotime($fromto[1]));
				}
				unset($data['pos_charting_fromto']);
				$data['pos_updated_date']=date('Y-m-d H:i:s');
				$data['pos_modified_by']=$this->view->user->subuser_id;
				$s=$this->SuperModel->Super_Insert("_uhs_pos",$data,'pos_id="'.$pos_id.'"');
				
				for($i=0;$i<$totalmadication;$i++){
					$madicationDatanew=array();
					$madicationDatanew['pos_medication_brand']=$madicationData['pos_medication_brand'][$i];
					$madicationDatanew['pos_medication_name']=$madicationData['pos_medication_name'][$i];
					$madicationDatanew['pos_medication_direction']=$madicationData['pos_medication_direction'][$i];
					$madicationDatanew['pos_medication_rx_number']=$madicationData['pos_medication_rx_number'][$i];
					//------------12.11.18------------------------
					$madicationDatanew['pos_add_blood_pressure']=$madicationData['pos_add_blood_pressure'][$i];
					$madicationDatanew['pos_add_blood_sugar']=$madicationData['pos_add_blood_sugar'][$i];
					$madicationDatanew['pos_add_bowel_movement']=$madicationData['pos_add_bowel_movement'][$i];
					//-----------------------------------------------------------------
					$madicationDatanew['pos_medication_odate']=date("Y-m-d",strtotime($madicationData['pos_medication_odate'][$i]));
					$madicationDatanew['pos_medication_frequency_type']=$madicationData['pos_medication_frequency_type'][$i];
					$remindVal=$madicationData['pos_medication_reminder'][$i];
					$madicationDatanew['pos_medication_reminder']=$remindVal;
					$madicationDatanew['pos_pphysician_fname']=$madicationData['pos_pphysician_fname'][$i];
					$madicationDatanew['pos_pphysician_lname']=$madicationData['pos_pphysician_lname'][$i];
					$madicationDatanew['pos_pphysician_address']=$madicationData['pos_pphysician_address'][$i];
					$madicationDatanew['pos_pphysician_phone']=$madicationData['pos_pphysician_phone'][$i];
					$madicationDatanew['pos_pphysician_email']=$madicationData['pos_pphysician_email'][$i];
					$madicationDatanew['pos_physician_refilno']=$madicationData['pos_physician_refilno'][$i];
					$madicationDatanew['pos_medication_prn']=$madicationData['pos_medication_prn'][$i];
					$madicationDatanew['pos_control_medication']=$madicationData['pos_control_medication'][$i];
					$madicationDatanew['pos_no_of_pills']=$madicationData['pos_no_of_pills'][$i];
					$madicationDatanew['pos_prescription_treatment']=$madicationData['pos_prescription_treatment'][$i];
					if($madicationDatanew['pos_prescription_treatment']=="Yes" || $madicationDatanew['pos_medication_prn']=="Yes"){
						$madicationDatanew['pos_prescription_routine']="No";
					}
					$madicationDatanew['pos_prescription_sideeffect']=$madicationData['pos_prescription_sideeffect'][$i];
					$madicationDatanew['medication_pos_id']=$pos_id;
					if($madicationData['pos_medication_id'][$i]!=""){
						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew,'medication_id='.$madicationData['pos_medication_id'][$i]);
						$mid=$madicationData['pos_medication_id'][$i];
					}
					else{
						$a=$this->SuperModel->Super_Insert("_uhs_pos_medication",$madicationDatanew);
						$mid=$a->inserted_id;
					}
					if($madicationData['pos_medication_id'][$i]!=""){
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_frequencies","umt_freq_medication_id=".$madicationData['pos_medication_id'][$i]);
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_times","umt_medication_id=".$madicationData['pos_medication_id'][$i]);
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Weekly"){
						for($k=0;$k<count($madicationData['feq_day'][$i]);$k++){
							$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>$madicationData['feq_day'][$i][$k],"umt_freq_times"=>$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]]);
							$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
							if(!empty($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]])){
								for($t=0;$t<count($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]]);$t++){
								$timeString="00:00:00";
								$isPrn=0;
								if($madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t]!="PRN"){
									$timeString=$madicationData['pos_medication_atime'][$i][$madicationData['feq_day'][$i][$k]][$t];
								}
								else{
									$isPrn=1;
								}
								$time_data=array('umt_medication_id'=>$mid,'umt_freq_id'=>$isIns->inserted_id,'umt_time'=>$timeString,'is_umt_prn'=>$isPrn,'umt_added'=>date("Y-m-d H:i:s"));
								if($isPrn==1){
									for($jk=1;$jk<=$madicationData['freq_times'][$i][$madicationData['feq_day'][$i][$k]];$jk++){
										$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
									}
								}
								else{
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							}
						}
					}
					if($madicationDatanew['pos_medication_frequency_type']=="Daily" || $madicationDatanew['pos_medication_frequency_type']=="As Needed"){
						$freqArr=array("umt_freq_medication_id"=>$mid,"umt_freq_pos_id"=>$pos_id,"umt_freq_days"=>0,"umt_freq_times"=>$madicationData['freq_times'][$i]);
						$isIns=$this->SuperModel->Super_Insert("_uhs_medication_frequencies",$freqArr);
						if(!empty($madicationData['pos_medication_atime'][$i])){
							for($t=0;$t<count($madicationData['pos_medication_atime'][$i]);$t++){
							$timeString="00:00:00";
							$isPrn=0;
							if($madicationData['pos_medication_atime'][$i][$t]!="PRN"){
								$timeString=$madicationData['pos_medication_atime'][$i][$t];
							}
							else{
								$isPrn=1;
							}
							$time_data=array('umt_medication_id'=>$mid,'umt_freq_id'=>$isIns->inserted_id,'umt_time'=>$timeString,'is_umt_prn'=>$isPrn,'umt_added'=>date("Y-m-d H:i:s"));
							if($isPrn==1){
								for($jk=1;$jk<=$madicationData['freq_times'][$i];$jk++){
									$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
								}
							}
							else{
								$n=$this->SuperModel->Super_Insert("_uhs_medication_times",$time_data);
							}
						}
						}
					}
				}
				$healthSession->successMsg=" TAR (Treatment Order Sheet) for user '".$data['pos_patient_fname'].' '.$data['pos_patient_lname']."' has been updated successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
		 $this->render('addtar');
	}
	
	public function discontinuetarAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->pageHeading="Discontinue Medication ".ucwords($po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname'])." ";
		$redirect_route="physician_order_sheet";
 		$form = new Application_Form_Emar();
		$avail_data=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='0'",'fetch',array('fields'=>array('medication_id')));
		$this->view->avail_data=$avail_data;
		$form->pos_discontinue($pos_id);
		$form->populate($po_data);
		$users=array("_uhs_agency","agency_id=pos_discontinue_name",'left',array('agency_first_name','agency_last_name'));
		$dis_continue_med=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_discontinue_status='1'",'fetchAll',array('fields'=>array('*'),'group'=>'medication_id'),array(0=>$users));
		$this->view->dis_continue_med=$dis_continue_med;
		if($this->getRequest()->isPost()) {
			$posted_data = $this->getRequest()->getPost();	
   			if($form->isValid($posted_data)){
				$data=$form->getValues();
				$med_id=$data['medication_id'];
				unset($data['medication_id']);
				$data['pos_discontinue_name']=$this->view->user->agency_id;
				$data['pos_discontinue_status']=1;
				$data['pos_discontinue_datetime']=date("Y-m-d H:i:s");
				$po_data=$this->SuperModel->Super_Insert("_uhs_pos_medication",$data,"medication_id='".$med_id."'");
				$healthSession->successMsg="Discontinue for the medication is done successfully";
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}else{
				$healthSession->errorMsg = "Please check information again.";
 			}
		 }
  		 $this->view->form =$form;
		 $this->render('discontinue');
	}
	
	public function viewtarAction(){
		global $healthSession,$systemusers; 
		if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}
		}
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}	
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$this->view->med_po_data=$med_po_data;
		
		$this->view->pageHeading="Treatment Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
	}
	
	public function viewtarreportAction(){
		global $healthSession;
		$this->view->pageHeading="TAR Report";
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$med=array('_uhs_pos_medication','umt_medication_id=medication_id','left',array());
		$pos=array('_uhs_pos','pos_id=medication_pos_id','left',array());
		
		$atimes=$this->SuperModel->Super_Get("_uhs_medication_times","pos_id='".$pos_id."'",'fetchall',array('fields'=>array((new Zend_Db_Expr("DISTINCT CONCAT(MONTHNAME(umt_added), '/', YEAR(umt_added)) AS Month" ))),'order'=>'umt_added DESC'),array(0=>$med,1=>$pos));
		$this->view->time_arr=$atimes;
		$this->view->med_po_data=$med_po_data;
		$this->view->pageHeading="Medication Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
		$html = $this->view->render('emar/viewtarreport.phtml');
		require_once(ROOT_PATH.'/private/mpdf/mpdf.php');
		$mpdf=new mPDF('utf-8', 'A3');
                
		$stylesheet = file_get_contents(APPLICATION_URL.'/public/plugins/bootstrap/css/bootstrap.css');
		$stylesheet1 = file_get_contents(APPLICATION_URL.'/public/front_css/style_custom.css');
		$mpdf->SetTitle($this->view->site_configs['site_name']." | ".ucwords($indi_data['agency_first_name']." ".$indi_data['agency_last_name'])." | View TAR");
		$mpdf->WriteHTML($stylesheet,1);
		$mpdf->WriteHTML($stylesheet1,1);
		$mpdf->WriteHTML($html,2);
		
		$mpdf->shrink_tables_to_fit=1;
		$mpdf->keep_table_proportions = true;
		$mpdf->Output();
		exit();
	}
	
	/* Common Code */
	public function removemedicationAction(){	
		global $healthSession;
		$medid=$_REQUEST['medId'];
		$medData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_id=".$medid);
		if(!empty($medData)){
			$isRemoved=$this->SuperModel->Super_Delete("_uhs_pos_medication","medication_id=".$medid);
		}
		exit();
	}
	public function tarchartAction(){
		global $healthSession,$systemusers,$pharmacyusers;
		$task=$_REQUEST['task'];
		$flag=0;
		if(isset($_POST) && ($_POST['medication_id']!='')){
			$amend_data['umc_code'] = $_POST['umc_code'];
			$amend_data['medication_id'] = $_POST['medication_id'];
			$amend_data['date'] = date("Y-m-d");
			$amend_data['user_id'] = $this->view->user->agency_id;
			$amend_data['create_date'] = date("Y-m-d H:i");
			// echo"<pre>";print_r($amend_data);
			// print_r($_POST);die;
			$getMedData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_id=".$_POST['medication_id']);
			$data=array('umc_medication_id'=>$_POST['medication_id'],'umc_med_time_id'=>$_POST['umc_med_time_id'],'umc_code'=>$_POST['umc_code'],'umc_time'=>$_POST['umc_time'],"umc_freq_id"=>$_POST['umc_freq_id'],"umc_prn"=>$_POST['umc_prn'],'umc_date'=>date('Y-m-d'),'umc_added_by'=>$this->view->user->subuser_id,'umc_added_date'=>date('Y-m-d H:i:s'),"umc_comments"=>"");
			if($_POST['umc_prn']=='1'){
				$get_chart_data=$this->SuperModel->Super_Get("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and umc_med_time_id='".$_POST['umc_med_time_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'",'fetch');
			}
			else{
				$get_chart_data=$this->SuperModel->Super_Get("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'",'fetch');
			}
			if(empty($get_chart_data)){
				if(!empty($_POST['umc_code'])){
					$x=$this->SuperModel->Super_Insert("_uhs_medication_charting",$data);
					$lastId=$x->inserted_id;
					
					// by sahil : START
					//$this->SuperModel->Super_Insert('_uhs_pos_amend', $amend_data);
					// by sahil : END

					// by sanjay : START
					$current_date = date("d");
					$save_date = date("d", strtotime($amend_data['date']));

					if($current_date != $save_date) {
						//$this->SuperModel->Super_Insert('_uhs_pos_amend', $amend_data);
					}
					// by sanjay : END
					
					if((in_array($this->view->user->agency_user_type,$systemusers) || in_array($this->view->user->agency_user_type,$pharmacyusers) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id!=0 && $this->view->user->is_subuser=="0")) && checkNotifySettings($this->view->user->agency_user_agency_id,"emar-chart")){
						$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>"emar-chart","notification_type_id"=>$lastId,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
						
						$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);
					}
				}
			}
			else{
				if(!empty($_POST['umc_code'])){
					$x=$this->SuperModel->Super_Insert("_uhs_medication_charting",$data,'umc_id="'.$get_chart_data['umc_id'].'"');
					$lastId=$get_chart_data['umc_id'];
				}
				else{
					if($_POST['umc_prn']=='1'){
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and umc_med_time_id='".$_POST['umc_med_time_id']."' and umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'");
					}
					else{
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'");
					}
					$flag=1;
				}
			}
			$form = new Application_Form_Emar();
			$commentingCodeArr=array("2"=>"2","3"=>"3","5"=>"5","6"=>"6");
			if(in_array($_POST['umc_code'],$commentingCodeArr)){
				$form->comments($lastId,$task);
				echo "Add Reason~~".$form;
			}
			else{
				if($flag==0){
					$healthSession->successMsg="Code has been assigned.";
				}
				else{
					$healthSession->successMsg="Code has been removed.";
				}
				if($task=="mar"){
					echo "mar~~".$this->view->url(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_mar_order_sheet",true);
				}
				else{
					echo "tar~~".$this->view->url(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_tar_order_sheet",true);
				}
			}
		}
		exit();
	}
	public function chartAction(){
                
		global $healthSession,$systemusers,$pharmacyusers;
                 $incRedirect = false;
              
		$task='';
		$flag=0;
                // prd($_POST['postData']);
                // die;
		$b_data =array();
		$bg_data =array();
		$bw_data=array();
		$indid = $_POST['indid'];

	    foreach ($_POST['postData'] as $_POST){
        //---------------02.11.18(BP)-----------------------------------
	    	$b_data['reading_date'] = date("Y-m-d",strtotime($_POST['reading_date']));
            $b_data['reading_time'] = $_POST['reading_time'];
            $b_data['heart_rate'] = $_POST['heart_rate'];
            $b_data['systolic'] = $_POST['systolic'];
            $b_data['diastolic'] = $_POST['diastolic'];
            $b_data['reading_source'] = $_POST['reading_source'];
            $b_data['reading_other_source'] = $_POST['reading_other_source'];
            $b_data['b_comments'] = $_POST['b_comments'];	
            $b_data['b_ind_id'] = $indid;
            $b_data['b_created_by']= $this->view->user->subuser_id; 
			$b_data['bcreated_on']= date('Y-m-d H:i:s');
			
            $bp = $this->SuperModel->Super_Insert('_uhs_blood_pressure',$b_data);
            
        //---------------06.11.18(BG)-----------------------------------

	    	$bg_data['blood_glucose'] = $_POST['blood_glucose'];
            $bg_data['bg_reading_date'] = date("Y-m-d",strtotime($_POST['bg_reading_date']));
            $bg_data['bg_reading_time'] = $_POST['bg_reading_time'];
            $bg_data['bg_reading_type'] = $_POST['bg_reading_type'];
            $bg_data['bg_reading_other_type'] = $_POST['bg_reading_other_type'];
            $bg_data['bg_action_taken'] = $_POST['bg_action_taken'];
            $bg_data['bg_other_action_taken'] = $_POST['bg_other_action_taken'];
            $bg_data['bg_other_action_taken'] = $_POST['bg_other_action_taken'];
            $bg_data['bg_reading_source'] = $_POST['bg_reading_source'];
            $bg_data['bg_reading_other_source']=	$_POST['bg_reading_other_source'];
            $bg_data['bg_comments']= $_POST['bg_comments'];
            $bg_data['bg_ind_id'] = $indid;
            $bg_data['bg_created_by']= $this->view->user->subuser_id; 
			$bg_data['bg_added_on']= date('Y-m-d H:i:s');

            $bg = $this->SuperModel->Super_Insert('_uhs_blood_glucose',$bg_data);

         //---------------08.11.18(BM)-----------------------------------

	    	$bw_data['bow_question_1'] = $_POST['bow_question_1'];
            $bw_data['bowel_service_date'] = date("Y-m-d",strtotime($_POST['bowel_service_date']));
            $bw_data['bow_question_2'] = $_POST['bow_question_2'];
            $bw_data['bow_question_3'] = $_POST['bow_question_3'];
            $bw_data['bow_question_4'] = $_POST['bow_question_4'];
            $bw_data['bow_agency_id']= $this->view->user->agency_id; 
			$bw_data['bow_individual_id']= $indid;
		    $bw_data['bow_movement_form_id']=18;
			$bw_data['bow_added_date']=date('Y-m-d H:i:s');
			$bw_data['bow_added_user']=$this->view->user->subuser_id;

			//prd($bw_data);
			//die;
            
            $bw = $this->SuperModel->Super_Insert('_uhs_bow_movement_logs',$bw_data);

		//---------------------------------------------------------------------
		  if(isset($_POST) && ($_POST['medication_id']!='')){
			$amend_data['umc_code'] = $_POST['umc_code'];
			$amend_data['medication_id'] = $_POST['medication_id'];
			$amend_data['date'] = date("Y-m-d");
			$amend_data['user_id'] = $this->view->user->agency_id;
			$amend_data['create_date'] = date("Y-m-d H:i");
            $task  = $_POST['task'];
			$getMedData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_id=".$_POST['medication_id']);
			$data=array('umc_medication_id'=>$_POST['medication_id'],'umc_med_time_id'=>$_POST['umc_med_time_id'],'umc_code'=>$_POST['umc_code'],'umc_time'=>$_POST['umc_time'],"umc_freq_id"=>$_POST['umc_freq_id'],"umc_prn"=>$_POST['umc_prn'],'umc_date'=>date('Y-m-d'),'umc_added_by'=>$this->view->user->subuser_id,'umc_added_date'=>date('Y-m-d H:i:s'),"umc_comments"=>$_POST['comment']);
			if($_POST['umc_prn']=='1'){
				$get_chart_data=$this->SuperModel->Super_Get("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and umc_med_time_id='".$_POST['umc_med_time_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'",'fetch');
			}
			else{
				$get_chart_data=$this->SuperModel->Super_Get("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'",'fetch');
			}
            
			if(empty($get_chart_data)){
				if(!empty($_POST['umc_code'])){
					$x=$this->SuperModel->Super_Insert("_uhs_medication_charting",$data);
					$lastId=$x->inserted_id;
					if($_POST['umc_code']== 5 && (in_array($this->view->user->agency_user_type,$systemusers) || $this->view->user->agency_user_type=='Agency')){
						$healthSession->incRedirect=true;
                                                //$incRedirect = true;
					}
					// by sahil : START
					//$this->SuperModel->Super_Insert('_uhs_pos_amend', $amend_data);
					// by sahil : END

					// by sanjay : START
					$current_date = date("d");
					$save_date = date("d", strtotime($amend_data['date']));

					if($current_date != $save_date) {
						//$this->SuperModel->Super_Insert('_uhs_pos_amend', $amend_data);
					}
					// by sanjay : END
					
					if((in_array($this->view->user->agency_user_type,$systemusers) || in_array($this->view->user->agency_user_type,$pharmacyusers) || ($this->view->user->agency_user_type=="Agency" && $this->view->user->agency_user_agency_id!=0 && $this->view->user->is_subuser=="0")) && checkNotifySettings($this->view->user->agency_user_agency_id,"emar-chart")){
						$notifyData=array("notification_user_id"=>$this->view->user->agency_user_agency_id,"notification_type"=>"emar-chart","notification_type_id"=>$lastId,"notification_by_user_id"=>$this->view->user->agency_id,"notification_date"=>date("Y-m-d H:i:s"));
						
						$isNotify=$this->SuperModel->Super_Insert("_uhs_notifications",$notifyData);
					}
                                        
                                        
				}
			}
			else{
				if(!empty($_POST['umc_code'])){
					$x=$this->SuperModel->Super_Insert("_uhs_medication_charting",$data,'umc_id="'.$get_chart_data['umc_id'].'"');
					$lastId=$get_chart_data['umc_id'];
                                        if($_POST['umc_code']==5 && (in_array($this->view->user->agency_user_type,$systemusers) || $this->view->user->agency_user_type=='Agency')){
						$healthSession->incRedirect=true;
						//$incRedirect = true;
					}
				}
				else{
					if($_POST['umc_prn']=='1'){
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and umc_med_time_id='".$_POST['umc_med_time_id']."' and umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'");
					}
					else{
						$isDel=$this->SuperModel->Super_Delete("_uhs_medication_charting","umc_medication_id='".$_POST['medication_id']."' and  umc_time='".$_POST['umc_time']."' and umc_date='".date('Y-m-d')."'");
					}
					//$flag=1;
				}
			}
			//$form = new Application_Form_Emar();
			//$commentingCodeArr=array("2"=>"2","3"=>"3","5"=>"5","6"=>"6");
			//if(in_array($_POST['umc_code'],$commentingCodeArr)){
				//$form->comments($lastId,$task);
				//echo "Add Reason~~".$form;
			//}
			//else{
				//~ if($flag==0){
					//~ $healthSession->successMsg="Code has been assigned.";
				//~ }
				//~ else{
					//~ $healthSession->successMsg="Code has been removed.";
				//~ }
				//~ if($task=="mar"){
					//~ echo "mar~~".$this->view->url(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_mar_order_sheet",true);
				//~ }
				//~ else{
					//~ echo "tar~~".$this->view->url(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_tar_order_sheet",true);
				//~ }
			//}
		}
            }
            if($flag==0){
                $healthSession->successMsg="Code has been assigned.";
            }
            else{
                $healthSession->successMsg="Code has been removed.";
            }
            if($task=="mar"){
                     // $this->_helper->getHelper("Redirector")->gotoRoute(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_mar_order_sheet");
            }else{
                      $this->_helper->getHelper("Redirector")->gotoRoute(array("pos_id"=>$getMedData['medication_pos_id']),"view_physician_tar_order_sheet");
            }
            
            exit();

	}
	
	public function savechartcommentsAction(){
		global $healthSession,$systemusers;
		$id=$this->_getParam("id");
		$getMedData=$this->SuperModel->Super_Get("_uhs_medication_charting","umc_id=".$id);
		$getPosData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_id=".$getMedData['umc_medication_id']);
		// print_r($_POST);die;
		$task=$this->_getParam("task");
		$form = new Application_Form_Emar();
		$form->comments($id,$task);
		if($this->getRequest()->isPost()){
			$posted_data=$this->getRequest()->getPost();
   			if($form->isValid($posted_data)){
				unset($posted_data['bttnsubmit']);
				$dataArr=array("umc_comments"=>$posted_data['comments']);
				$isUpdate=$this->SuperModel->Super_Insert("_uhs_medication_charting",$dataArr,"umc_id=".$id);
				if($isUpdate->success){
					$healthSession->successMsg="Code has been added";
					if(!empty($getMedData) && $getMedData['umc_code']==5 && (in_array($this->view->user->agency_user_type,$systemusers) || $this->view->user->agency_user_type=='Agency')){
						$healthSession->incRedirect=true;
					}
				}
			}
			else{
				$healthSession->errorMsg="There is a problem.";
			}
		}
		if($task=="mar"){
			$this->_helper->getHelper("Redirector")->gotoRoute(array("pos_id"=>$getPosData['medication_pos_id']),"view_physician_mar_order_sheet");
		}
		else{
			$this->_helper->getHelper("Redirector")->gotoRoute(array("pos_id"=>$getPosData['medication_pos_id']),"view_physician_tar_order_sheet");
		}
	}
	
	public function getuserdetailAction(){
		global $healthSession;
		$agency_id=$this->_getParam('agency_id'); 
		$data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$agency_id."'",'fetch',array('fields'=>array('agency_first_name','agency_last_name','agency_email','agency_gender','agency_address1','agency_dob','agency_cell_phone','agency_email','agency_medicaid_number','agency_medicare_number')));
		$data['mar_status']=0;
		$data['tar_status']=0;
		if(!empty($data['agency_dob']) && $data['agency_dob']!="0000-00-00"){
			$data['agency_dob']=date('m/d/Y',strtotime($data['agency_dob']));
		}
		$get_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$agency_id.'" and mar_status="1" and pos_status="0"');
		if(!empty($get_pos)){
			$data['mar_status']=1;
		}
		$get_data_pos=$this->SuperModel->Super_Get("_uhs_pos",'pos_agent_id="'.$agency_id.'" and tar_status="1" and pos_status="0"');
	
		if(!empty($get_data_pos)){
			$data['tar_status']=1;
		}
		echo json_encode($data);
		exit();
	}
	
	public function publishAction(){
		global $healthSession,$systemusers,$pharmacyusers;
		$type=$this->_getParam("type");
		if($type=="pos"){
			$redirect_route="physician_order_sheet";
		}
		else if($type=="mar"){
			$redirect_route="physician_mar_order_sheet";
		}
		else{
			$redirect_route="physician_tar_order_sheet";
		}
		$pos_id=$this->_getParam('pos_id');
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
 		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$data=array('pos_published_status'=>1);
		$this->SuperModel->Super_Insert("_uhs_pos",$data,'pos_id="'.$pos_id.'"');
		$healthSession->successMsg="eMAR  Published Successfully.";
		$this->_helper->getHelper("Redirector")->gotoRoute(array(),$redirect_route);	
	}
	
	public function removeposAction(){
		global $healthSession;
 		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$type=$this->_getParam("type");
		$data=$this->getRequest()->getPost();
		if(isset($data['_uhs_pos']) && count($data['_uhs_pos'])) {
			foreach($data['_uhs_pos'] as $key=>$values){
  			$removed=$this->SuperModel->getAdapter()->delete('_uhs_pos',"pos_id='".$values."'");
 			$healthSession->successMsg="Record has been deleted successfully.";
 		  }
		}
		else{
			$healthSession->errorMsg = "Invalid request to delete.";
		}
		if($type=="pos"){$redirect_route="physician_order_sheet";}
		if($type=="mar"){$redirect_route="physician_mar_order_sheet";}
		if($type=="tar"){$redirect_route="physician_tar_order_sheet";}
		$this->_helper->getHelper("Redirector")->gotoRoute(array(),$redirect_route);	
	}
	
	public function viewnotesAction(){
		global $healthSession;
		$posid=$this->_getParam("pos_id");
		$type=$this->_getParam("type");
		$this->view->posid=$posid;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$posid."'",'fetch');	
		if(empty($po_data)){
			$healthSession->errorMsg="No Record Found.";
			if($type=="tar"){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			}else{
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}
		}
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 $healthSession->errorMsg="No Individual Found.";
			 if($type=="tar"){
			 	$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
			 }else{
			 	$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			 }
		}
		$this->view->type=$type;
		if($type=="tar"){
			$this->view->pageHeading="TAR Notes";
		}
		else{
			$this->view->pageHeading="MAR Notes";
		}
	}
	//-------------------------------------modified 31.10.18--------------------------------
	public function getnotesAction(){
		global $healthSession,$time_array;
		$posid=$this->_getParam("posid");
		$type=$this->_getParam("type");
 		$this->dbObj = Zend_Registry::get('db');
		$aColumns = array('umc_id','umc_medication_id','umc_date','umc_code','umc_comments','umc_added_by','umc_added_date','umc_time','umc_prn');
		$sIndexColumn = 'umc_id';
		$sTable = '_uhs_medication_charting';
		$sLimit = "";
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' ){
			$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".intval( $_GET['iDisplayLength'] );
		}
		$sOrder = "";
		if(isset($_GET['iSortCol_0'])){
			$sOrder = "ORDER BY  ";
			for($i=0;$i<intval($_GET['iSortingCols']);$i++){
				if($_GET['bSortable_'.intval($_GET['iSortCol_'.$i])]=="true"){
					$sOrder.="".$aColumns[ intval( $_GET['iSortCol_'.$i] ) ]." ".
						($_GET['sSortDir_'.$i]==='asc' ? 'asc' : 'desc') .", ";
				}
			}
			$sOrder=substr_replace($sOrder,"",-2);
			if ( $sOrder == "ORDER BY" ){
				$sOrder = "";
			}
		}
		$sWhere = "";
		if ( isset($_GET['sSearch']) and $_GET['sSearch'] != "" ){
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ ){
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET["sSearch"]."%' OR "; // NEW CODE
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		for($i=0;$i<count($aColumns);$i++){
			if(isset($_GET['bSearchable_'.$i]) and $_GET['bSearchable_'.$i] == "true" and $_GET['sSearch_'.$i] != '' ){
				if($sWhere==""){
					$sWhere = "WHERE ";
				}
				else{
					$sWhere .= " AND ";
				}
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch_'.$i]."%' ";
			}
		}
		if($type=="mar"){
			$medData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_pos_id=".$posid." and (pos_prescription_routine='Yes' OR pos_medication_prn='Yes')",'fetchAll',array("fields"=>"medication_id"));
		}
		else{
			$medData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_pos_id=".$posid." and pos_prescription_treatment='Yes'",'fetchAll',array("fields"=>"medication_id"));
		}
		$medIds=0;
		if(count($medData)>0){
			$medIds=implode_r(",",$medData);
		}
		if($sWhere){
			$sWhere.=" and umc_medication_id IN(".$medIds.")"; 
		}
		else{
			$sWhere.=" where umc_medication_id IN(".$medIds.")"; 
		}
		$sQuery = "SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $aColumns))." from $sTable $sWhere $sOrder $sLimit";
 		$qry = $this->dbObj->query($sQuery)->fetchAll();
		$sQuery = "SELECT FOUND_ROWS() as fcnt";
		$aResultFilterTotal =  $this->dbObj->query($sQuery)->fetchAll(); 
		$iFilteredTotal = $aResultFilterTotal[0]['fcnt'];
		$sQuery = "SELECT COUNT(`".$sIndexColumn."`) as cnt FROM $sTable ";
		$rResultTotal = $this->dbObj->query($sQuery)->fetchAll(); 
		$iTotal = $rResultTotal[0]['cnt'];
		$output = array(
 				"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
			);
		$j=0;
		foreach($qry as $row1){
			global $code_full,$codes;
			$posData=$this->SuperModel->Super_Get("_uhs_pos","pos_id=".$posid);

			//------------------------31.10.18------------------------------------------------
			$blood_glucose=$this->SuperModel->Super_get("_uhs_blood_glucose","bg_ind_id='".$posData['pos_agent_id']."' AND bg_added_on='".$row1['umc_added_date']."'",'fetch',array("order"=>"gid DESC"));

			//$blood_pressure=$this->SuperModel->Super_get("_uhs_blood_pressure","b_ind_id='".$posData['pos_agent_id']."' AND DATE(bcreated_on)='".date("Y-m-d", strtotime($row1['umc_added_date']))."' AND HOUR(bcreated_on)='".date('H', strtotime($row1['umc_added_date']))."'",'fetch',array("order"=>"bid DESC",'limit'=>1));
            $blood_pressure=$this->SuperModel->Super_get("_uhs_blood_pressure","b_ind_id='".$posData['pos_agent_id']."' AND bcreated_on='".$row1['umc_added_date']."'",'fetch',array("order"=>"bid DESC"));

            $bowel_movement=$this->SuperModel->Super_get("_uhs_bow_movement_logs","bow_individual_id='".$posData['pos_agent_id']."' AND bow_added_date='".$row1['umc_added_date']."'",'fetch',array("order"=>"bow_movement_id DESC"));
			//-----------------------------------------------------------------------------------
			$medData=$this->SuperModel->Super_Get("_uhs_pos_medication","medication_id=".$row1['umc_medication_id']);
			$chartAddedBy=$this->SuperModel->Super_Get("_uhs_agency","agency_id=".$row1['umc_added_by']);
			$row=array();
 			$row[]=$j+1;

 			if($blood_pressure['systolic']!='' && $medData['pos_add_blood_pressure']==1){

 			 $row[]='Systolic:'.$blood_pressure['systolic'].' Diastolic:'.$blood_pressure['diastolic'];

 			} else{

 			 $row[]='NA';

 			}

 			if($blood_glucose['blood_glucose']!='' && $medData['pos_add_blood_sugar']==1){

 			 $row[]=$blood_glucose['blood_glucose'];

 			} else{

 			 $row[]='NA';

 			}
 			
 			if($bowel_movement['bow_movement_id']!='' && $medData['pos_add_bowel_movement']==1){

 			 $row[] = '<a href="'.APPLICATION_URL.'/blow-movement/'.$posData['pos_agent_id'].'/18'.'" class="" target="_blank">View Logs</a>';

 			} else{

 			 $row[]='NA';

 			}

			$row[]=ucwords($posData['pos_patient_fname']." ".$posData['pos_patient_lname']);
			$row[]=isEmpty($medData['pos_medication_brand']);
			$row[]=isEmpty($medData['pos_medication_name']);
			if($row1['umc_time']=="00:00:00" && $row1['umc_prn']=='1'){
				$row[]="PRN";
			}
			else{
  				$row[]=$time_array[$row1['umc_time']];
			}
			$row[]="<span class='badge badge-success' style='cursor:help;' title='".$code_full[$codes[$row1['umc_code']]]."'>".$codes[$row1['umc_code']]."</span>";
			$row[]=isEmpty($row1['umc_comments']);
			$userType=printUserType($chartAddedBy);
			$row[]=ucwords($chartAddedBy['agency_first_name']." ".$chartAddedBy['agency_last_name'])."<br/>".$userType;
			$row[]=formatDateTimeNew($row1['umc_added_date']);
			$output['aaData'][] = $row;
			$j++;
		}
		echo json_encode($output);
		exit();
	}
	//---------------------------------------------------------------------------------------------------
	public function viewmarchartinghistoryAction(){
           // ini_set("display_errors", "1"); error_reporting(E_ALL);
            
            global $healthSession,$systemusers; 
// 		print_r($this->view->user->agency_user_type);die;
		/*if($this->view->user->agency_user_type=="Agency" || in_array($this->view->user->agency_user_type,$systemusers)){
			if(!isset($healthSession->emarSecurity) || empty($healthSession->emarSecurity) || $healthSession->emarSecurity!=1){
				$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
			}
		}*/
		
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
                $archid=$this->_getParam("archid");
                $this->view->current_action=$this->view->current_action;
                $po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		
		if(empty($po_data)){
			 $healthSession->errorMsg="No Record Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		/*if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_mar_order_sheet");
		}*/
		
		$this->view->indi_parent_data=$indi_parent_data;
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
// 		ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
		$amend = array();
		$amendumct = array();
		$ammend2 = [];

		foreach($med_po_data as $value){
            $result_amend = $this->SuperModel->Super_get("_uhs_pos_amend","medication_id = ".$value['medication_id'], 'fetchAll');
            // if($result_amend != null && !empty($result_amend) && isset($result_amend['amend_id'])){
            //    $amend[$value['medication_id']][$result_amend['date']] = $result_amend;
            // }
 // echo "<pre>"; print_r($result_amend[0]['date']); die;

            if(count($result_amend[0])>0){
            	foreach($result_amend as $am_res){
            		   $amend_user = $this->SuperModel->Super_get("_uhs_agency","agency_id = ".$am_res['user_id']);
            		   // print_r($amend_user); die;
            		   $am_res['created_by'] = $amend_user['agency_first_name']." ".$amend_user['agency_last_name'];
          $amend[$value['medication_id']][$am_res['date']] = $am_res;
          $amendumct[$value['medication_id']][$am_res['date']][$am_res['umc_time']] = $am_res;
            	}
        	}
            $ammend2[] = $result_amend;

            
        }
    //  echo "<pre>";print_r($amend);die;
		$this->view->med_po_data = $med_po_data;
		$this->view->amend_data = $amend;
		$this->view->amendumct_data = $amendumct;

		
		$med=array('_uhs_pos_medication','umt_medication_id=medication_id','left',array());
		$pos=array('_uhs_pos','pos_id=medication_pos_id','left',array());
		
		$atimes=$this->SuperModel->Super_Get("_uhs_medication_times","pos_id='".$pos_id."'",'fetchall',array('fields'=>array((new Zend_Db_Expr("DISTINCT CONCAT(MONTHNAME(umt_added), '/', YEAR(umt_added)) AS Month" ))),'order'=>'umt_added DESC'),array(0=>$med,1=>$pos));
	//echo"<pre>";print_r($med_po_data4);die();
		$this->view->time_arr=$atimes;
		$this->view->pageHeading="Medication Administration Record for ".$po_data['pos_patient_fname'].' '.$po_data['pos_patient_lname']." ";
		$this->view->sub_pageHeading=$po_data['pos_patient_address'];
                $this->view->archid=$archid;
        }
	
	public function viewtarchartinghistoryAction(){
		global $healthSession,$systemusers; 
		$pos_id=$this->_getParam('pos_id'); 
		$this->view->pos_id=$pos_id;
		$po_data=$this->SuperModel->Super_get("_uhs_pos","pos_id='".$pos_id."'",'fetch');
		if(empty($po_data)){
			$healthSession->errorMsg="No Record Found";
			$this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->po_data=$po_data;
		$indi_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$po_data['pos_agent_id']."'",'fetch');
		$this->view->indi_data=$indi_data;
		$indi_parent_data=$this->SuperModel->Super_get("_uhs_agency","agency_id='".$indi_data['agency_user_agency_id']."'",'fetch');
		$permission=checkIndividualPermissions($indi_data['agency_id']);
		if(empty($permission)){
			 global $healthSession;
			 $healthSession->errorMsg="No Individual Found.";
			 $this->_helper->getHelper("Redirector")->gotoRoute(array(),"physician_tar_order_sheet");
		}
		$this->view->indi_parent_data=$indi_parent_data;
		
		$joinArr=array("0"=>array("_uhs_medication_frequencies","umt_freq_medication_id=medication_id","full",array('*')));
		
		$med_po_data1=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data5=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data2=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='No'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data3=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Daily' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
                $med_po_data6=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='As Needed' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data4=$this->SuperModel->Super_get("_uhs_pos_medication","medication_pos_id='".$pos_id."' and pos_medication_frequency_type='Weekly' and pos_medication_prn='Yes'",'fetchAll',array("order"=>"umt_freq_times ASC","group"=>"umt_freq_medication_id"),$joinArr);
		
		$med_po_data=array_merge($med_po_data1,$med_po_data2,$med_po_data3,$med_po_data4,$med_po_data5,$med_po_data6);
		
		$this->view->med_po_data=$med_po_data;
		$this->view->pageHeading="TAR Charting History";
	}
	
	public function unsetsecuritycheckAction(){
		global $healthSession;
		if(isset($healthSession->emarSecurity)){ unset($healthSession->emarSecurity);}
		exit();
	}
}
