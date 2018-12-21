<?php
class Application_Model_SuperModel extends Zend_Db_Table_Abstract{
 	protected $_name = "";
	
	public function init(){}
	
	public function GetIndivisuals($user_id,$doc_type){	
		$loggedUserData=getFromView("user",true);
		$default_value="Select";
		global $all_users;
		if($doc_type==1){
			$n=' and pos_status="1"';
		}
		else if($doc_type==2){
			$n=' and mar_status="1"';
		}
		else if($doc_type==3){
			$n=' and tar_status="1"';
		}
		$userLogged = isLogged(true);
		$iData=$this->Super_Get("_uhs_agency","agency_user_type='Individual' and agency_user_agency_id=".$user_id." and agency_status='1'","fetchAll",array("fields"=>"agency_id"));	
		$indids="";
		if(count($iData)>0){
			$indids=implode_r(",",$iData);
		}
		/*$get_indi_data=$this->getAdapter()->select()->from(array('u1'=>'_uhs_individual_permission'),'group_concat(individual_id) as indis')->where('individual_permission="Edit" and permission_owner_id="'.$user_id.'"')->query()->fetch(); 
		if(!empty($get_indi_data['indis'])){
			if(!empty($indids)){
				$indids.=','.$get_indi_data['indis'];
			}
			else{
				$indids=$get_indi_data['indis'];
			}
		}*/
		if(!empty($indids)){
			$result = $this->getAdapter()->select()->from(array('u1'=>'_uhs_agency'),'*')->joinLeft(array('pos'=>'_uhs_pos'),'pos.pos_agent_id=u1.agency_id '.$n.'',array('pos_id'))->where("agency_user_type='Individual' and agency_id IN(".$indids.") and agency_status='1'");
			$data=$result->query()->fetchAll(); 
		}
		else{
			$data=array();
		}
		if(!empty($data)){
			$getdata=array();
			if($default_value){
				$getdata['']=$default_value;
			}
			for ($i = 0; $i < count($data); $i++){
				if(trim($data[$i]['pos_id'])==''){
					$getdata[rtrim($data[$i]['agency_id'])]= rtrim($data[$i]['agency_first_name'].' '.$data[$i]['agency_last_name'].'('.$data[$i]['agency_email'].')');
				}
			}
			return $getdata;
		}
		else{
			return $data;
		}
	}
	
	public function edit_amend($id, $data){
		// $this->modelEmail = new Application_Model_Email();
		$this->_name = "_uhs_pos_amend";
		$this->update($data, 'amend_id = '.$id);
		return true;
	}

	public function PrepareSelectOptions_withdefault($tabelname ,$fieldname1,$fieldname2,$where,$order,$default_value=false)
	{	
		
		if(!$order)
		$result = $this->getAdapter()->select()->from($tabelname)->where($where);
		else  
		$result = $this->getAdapter()->select()->from($tabelname)->where($where)->order($order);
		$data= $result->query()->fetchAll() ; 
		$getdata=array();
		if($default_value)
		{
		$getdata['']=$default_value;
		}
		for ($i = 0; $i < count($data); $i++) 
		{

		$getdata[$data[$i][$fieldname1]]= $data[$i][$fieldname2];

		}
		
		return $getdata;
	}
	
	public function customQuery($table,$fields,$where){
		 $this->_name=$table;
		 $data = $this->getAdapter()->select()->from($this->_name,new Zend_Db_Expr($fields))->where($where)->query()->fetchAll(); 
		 return $data;
	}
	
	public function checkEmail($email,$id=false,$userType){	
		$this->_name ="_uhs_agency";
		$query=$this->select()->where("agency_email='".$email."' and agency_draft_status='1'"); 
		if(!$id)
			return  $query->query()->fetch();	 	

 		    return  $query->where("agency_id != '".$id."'")->query()->fetch(); 	
 	}
	
	public function checkUserEmail($email,$id=false,$userType){	
		$this->_name ="_uhs_agency";
		$query=$this->select()->where("REPLACE(agency_email, ' ', '' )='".$email."'"); 
		// print_r($query);die;
		if(!$id)
			return  $query->query()->fetch();	 	

 		    return  $query->where("agency_id != '".$id."'")->query()->fetch(); 	
 	}
	
	public function checkFolderName($fname,$id, $creator_id, $visitor_id, $parent_id){	
		$this->_name ="_uhs_folders";
		$query =  $this->select()->where("folder_name='".$fname."' AND folder_creator ='".$creator_id."' AND folder_visiting_id='".$visitor_id."' AND parent_fid = '". $parent_id . "'");
		if(empty($id)){
			return  $query->query()->fetch();	 	
		}else{
 		    return  $query->where("fid != '".$id."'")->query()->fetch(); 
		}
 	}
	
	public function checkFileName($fname,$id){	
		$this->_name ="_uhs_files";
		$query =  $this->select()->where("file_name='".$fname."'"); 
		if(empty($id)){
			return  $query->query()->fetch();	 	
		}else{
 		    return  $query->where("file_id != '".$id."'")->query()->fetch(); 
		}
 	}
	public function checkBriefcaseFolderName($fname,$id, $creator_id, $visitor_id, $parent_id){	
		$this->_name ="_uhs_briefcase_folders";
		$query =  $this->select()->where("folder_name='".$fname."' AND folder_creator ='".$creator_id."' AND folder_visiting_id='".$visitor_id."' AND parent_fid = '". $parent_id . "'");
		if(empty($id)){
			return  $query->query()->fetch();	 	
		}else{
 		    return  $query->where("fid != '".$id."'")->query()->fetch(); 
		}
 	}
	
	public function checkBriefcaseFileName($fname,$id){	
		$this->_name ="_uhs_briefcase_files";
		$query =  $this->select()->where("file_name='".$fname."'"); 
		if(empty($id)){
			return  $query->query()->fetch();	 	
		}else{
 		    return  $query->where("file_id != '".$id."'")->query()->fetch(); 
		}
 	}
	
	public function fetchByJoin($table_name,$join_table,$join_condition,$condition,$order='',$limit='',$set_limit=''){
		$this->_name =$table_name;
		if(!empty($order)){
			if(!empty($limit)){
				$data = $this->getAdapter()->select()->from($this->_name)->join($join_table,$join_condition)->where($condition)->order($order)->limit($limit,$set_limit)->query()->fetchAll();  
			}
			else{
				$data = $this->getAdapter()->select()->from($this->_name)->join($join_table,$join_condition)->where($condition)->order($order)->query()->fetchAll();  
			}
		}
		else{
			if(!empty($limit)){
				$data = $this->getAdapter()->select()->from($this->_name)->join($join_table,$join_condition)->where($condition)->limit($limit,$set_limit)->query()->fetchAll(); 
			}
			else{
				$data = $this->getAdapter()->select()->from($this->_name)->join($join_table,$join_condition)->where($condition)->query()->fetchAll();  
			}
		}
		return $data;
	}
	
	public function getCount($param = array()){
		 $this->_name = isset($param['table'])? $param['table']: "users"; 
		 $field_name = isset($param['key'])?$param['key']:"user_id";
		 $where = isset($param['where'])?$param['where']:"1";
		 $data = $this->getAdapter()->select()->from($this->_name,new Zend_Db_Expr(" count($field_name) as count"))->where($where)->query()->fetch(); 
		 return $data['count'];
	}
	
	public function resetAgencyPassword($agency_email){
		$this->modelEmail = new Application_Model_Email();
		$this->_name = "_uhs_agency";
		$agencyData = $this->get("_uhs_agency",array("where"=>"agency_email='$agency_email'")) ;
		$reset_password_key = md5($agencyData['agency_id']."!@#$%^".$agencyData['agency_code'].time());
		$data_to_update = array("agency_reset_status"=>"1","agency_pass_resetkey"=>$reset_password_key);
        $this->update($data_to_update, 'agency_id = '.$agencyData['agency_id']);
		$agencyData['agency_pass_resetkey'] = $reset_password_key ;
 		$agencyData['agency_reset_status'] = "1" ;
		$email = $this->modelEmail->sendEmail('reset_password',$agencyData);
		if($email->success)
			return true;
	
 		return false ;  
	}
	
	public function get($table,$param = false ){
		 $this->_name = $table;
		 if(is_array($param)){
			 if(isset($param['key'])){
				$result = $this->fetchAll("agency_pass_resetkey='".$param['key']."'");
				if($result->count()){
					return $result->current()->toArray();
				}
				return false ;
 			 }
			 if(isset($param['where'])){
				$result = $this->fetchAll($param['where']);
				if($result->count()){
					return $result->current()->toArray();
				}
				return false ;
 			 }   			 
		 }
	 }
	 
 	public function Super_Insert($table_name ,$data , $where = false){	
		$this->_name = $table_name;
		try{			
			if($where){
				$updated_records = $this->getAdapter()->update($table_name ,$data , $where);
				return (object)array("success"=>true,"error"=>false,"message"=>"Record Successfully Updated","row_affected"=>$updated_records) ;
			}
			$insertedId = $this->getAdapter()->insert($table_name,$data); 
 			return (object)array("success"=>true,"error"=>false,"message"=>"Record Successfully Inserted","inserted_id"=>$this->getAdapter()->lastInsertId()) ;
 		}
		catch(Zend_Exception  $e) {
			return (object)array("success"=>false,"error"=>true,"message"=>$e->getMessage(),"exception"=>true,"exception_code"=>$e->getCode()) ;
 		}
	}
	
 	public function Super_Get($table_name , $where = 1, $fetchMode = 'fetch', $extra = array(),$joinArr=array()){ 
		$this->_name = $table_name;
		$fields = array('*');
		if(isset($extra['fields']) and  $extra['fields']){
			if(is_array($extra['fields'])){
				$fields = $extra['fields'];
			}else{
				$fields = explode(",",$extra['fields']);
			}
		}
		if(isset($extra['join']) and $extra['join']){
			$query=$this->getAdapter()->select()->from(array($this->_name),$fields)->join(array($extra['join']),$extra['joincondition'],$extra['joinfields'])->where($where);
		}
		else{
			$query  = $this->getAdapter()->select()->from($this->_name,$fields)->where($where);
		}
		/* Join Conditions */
		if(isset($joinArr)){
			foreach($joinArr as $newCondition){ 
				if($newCondition[2]=='full')
					$query->join($newCondition[0],$newCondition[1],$newCondition[3]);
				else
					$query->joinLeft($newCondition[0],$newCondition[1],$newCondition[3]);	
			}
		}
		if(isset($extra['group']) and  $extra['group']){
			$query = $query->group($extra['group']);
		}
		if(isset($extra['having']) and  $extra['having']){
			$query = $query->having($extra['having']);
		}
		if(isset($extra['order']) and  $extra['order']){
			$query = $query->order($extra['order']);
		}
		if(isset($extra['limit'])){
			if(isset($extra['offset'])){
				$query = $query->limit($extra['limit'],$extra['offset']);
			}
			else{
				$query = $query->limit($extra['limit']);
			}
		}
		if(isset($extra['test']) and  $extra['test']){
			return $query->query();
		}
		if(isset($extra['pagination']) and  $extra['pagination']){
			return $query;
		}
		// echo"<pre>";print_r($query);die;
		return $fetchMode=='fetch'? $query->query()->fetch():$query->query()->fetchAll();
	 }
	 
 	public function Super_Delete($table_name , $where = "1"){	
   		try{
			$deleted_records = $this->getAdapter()->delete($table_name ,  $where);
 			return (object)array("success"=>true,"error"=>false,"message"=>"Record Successfully Deleted","deleted_records"=>$deleted_records) ;
  		}
		catch(Zend_Exception  $e) {
			return (object)array("success"=>false,"error"=>true,"message"=>$e->getMessage(),"exception"=>true,"exception_code"=>$e->getCode()) ;
 		}
	}
	
	public function Get_AllIndividuals($agency_user_agency_id){	
		$this->_name ="_uhs_agency";
		$query =  $this->select()->where("agency_user_agency_id='".$agency_user_agency_id."' AND agency_user_type ='Individual'");
		return  $query->query()->fetchAll();	 	
	}
	
	public function Get_Emergencycontacts($contact_ind_id){	
		$this->_name ="_uhs_emergency_contact";
		$query =  $this->select()->where("contact_ind_id=".$contact_ind_id);
		return  $query->query()->fetchAll();	 	
	}
	public function Get_Physiciancontacts($contact_ind_id){	
		$this->_name ="_uhs_physician_contact";
		$query =  $this->select()->where("contact_ind_id=".$contact_ind_id);
		return  $query->query()->fetchAll();	 	
	}
	
	public function Get_fireloglatestadd($indid,$formid){
		//echo $indid."--";
		//echo $formid;exit;
	//	$order = "ORDER BY desc LIMIT 1";
		$this->_name ="_uhs_drills_logs";
		$query =  $this->select()->where("drill_individual_id=".$indid." AND drill_form_id=".$formid);
		
		return  $query->query()->fetchAll();
	}
        public function Get_events($ids){
		//echo $ids;exit;
		
		$this->dbObj = Zend_Registry::get('db');
		$agencyDataid = $this->dbObj->query("SELECT * FROM `_uhs_calendar_events` where event_id IN (".$ids.") GROUP BY `event_start_date`")->fetchAll();
		return $agencyDataid;
	}
	
	public function Get_creatorname($indid){
		$order = "ORDER BY desc LIMIT 1";
		$this->_name ="_uhs_agency";
		$query =  $this->select()->where("agency_id=".$indid);
		return  $query->query()->fetchAll();
	}
}