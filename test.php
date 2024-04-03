function get_state_incomplete_cases($link_pdo, $userid, $remote_server_ip, $requesttime, $webservicename = '', $till_date, $office_code, $params, $datafield, $date, $dashboard_type_code)
{
	$dashboard_type = 'ALL';

	// FETCH Data to be fetched based on dashboard type
	$sql_to_get_dashboard_type = "SELECT dashboard_type_name FROM revenue_gj.office_types WHERE id =:dashboard_type_code;";
	$stmt_to_get_dashboard_type = $link_pdo->prepare($sql_to_get_dashboard_type);
	$stmt_to_get_dashboard_type->execute(['dashboard_type_code' => $dashboard_type_code]);
	$dashboard_type = $stmt_to_get_dashboard_type->fetchColumn();

	if ($dashboard_type == 'ALL') 	// Includes SSRD and GRT
	{
		$sql_summary = "
			SELECT so.office_sname_sms, table1.total_cases FROM revenue_gj.sso_office_details so INNER JOIN
			(
			select mt.estcode,COUNT(*) AS total_cases
			from revenue_gj.main_t mt
			INNER JOIN revenue_gj.order_details od ON mt.surrogatekey = od.surrogatekey AND od.order_dt='0000-00-00'
			WHERE mt.district <> -1 
			GROUP BY mt.estcode
			UNION
			select mt.estcode,COUNT(*) AS total_cases
			from ahmedabad_ssrd.main_t mt
			INNER JOIN ahmedabad_ssrd.order_details od ON mt.surrogatekey = od.surrogatekey AND od.order_dt='0000-00-00'
			WHERE mt.district <> -1 
			GROUP BY mt.estcode
			) AS table1 ON table1.estcode = so.uid
			GROUP BY so.office_sname_sms,table1.estcode;";
	}
	else if ($dashboard_type == 'ALL_EXCEPT_SSRD_AND_GRT') 	// Excludes SSRD and GRT
	{
		$sql_summary = "SELECT so.office_sname_sms, table1.total_cases FROM revenue_gj.sso_office_details so INNER JOIN
			(
			select mt.estcode,COUNT(*) AS total_cases
			from revenue_gj.main_t mt
			INNER JOIN revenue_gj.order_details od ON mt.surrogatekey = od.surrogatekey AND od.order_dt='0000-00-00'
			WHERE mt.estcode <> 701 AND mt.district <> -1 
			GROUP BY mt.estcode) AS table1 ON table1.estcode = so.uid
			GROUP BY so.office_sname_sms,table1.estcode;";
	}
	else if ($dashboard_type == 'SSRD') 	// Level of Office Wise e.g. 
	{
		//office_id - SLR Appellate and reply accordingly then send details of SLR Only
		$sql_summary = "SELECT so.officename,
			CONCAT(ct.edesc,' / ', dm.District_code,' / ',mt.registration_no,' / ',mt.registration_yr) AS case_number,
			CASE WHEN(cls.clscnt IS NULL OR cls.clscnt = 0) THEN 0 ELSE 1 END AS classification_count,
			CASE WHEN(mt.case_stage IS NULL OR mt.case_stage = -1 OR mt.case_stage = '') THEN 0 ELSE 1 END AS case_stage,
			CASE WHEN ((mt.type_of_matter = 'S' or mt.type_of_matter = 'T')) THEN 0 ELSE CASE WHEN (lc.lccnt = 0 OR lc.lccnt IS NULL) THEN 0 ELSE 1 END END as lower_court,
			CASE WHEN (csn.surnocnt IS NULL OR csn.surnocnt = 0) AND (ccsn.csurnocnt IS NULL OR ccsn.csurnocnt = 0) THEN 0 ELSE 1 END AS land_details,
			CASE WHEN(pet.pcnt IS NULL OR pet.pcnt = 0) THEN 0 ELSE 1 END AS petitioner_count,
			CASE WHEN(res.rcnt IS NULL OR res.rcnt = 0) THEN 0 ELSE 1 END AS respondant_count,
			CASE WHEN(mt.full_case_details_entered IS NULL OR mt.full_case_details_entered = 'N') THEN 0 ELSE 1 END AS case_details_completed
			FROM revenue_gj.sso_office_details so 
			INNER JOIN ahmedabad_ssrd.main_t mt ON so.uid = mt.estcode
			INNER JOIN ahmedabad_ssrd.case_type_t ct ON mt.case_type_code = ct.code
			INNER JOIN revenue_gj.district_mst dm ON mt.district = dm.district_id
			left join
			(select count(*) as pcnt,surrogatekey,p_no as pet_p_no, 
			name as pet_name, CONCAT(add1, add2, add3, city) as paddress, 
			display as pet_display, lastupdatedtime as pet_lastupdatedtime from ahmedabad_ssrd.petitioner_t group by surrogatekey) pet
			ON mt.surrogatekey = pet.surrogatekey
			left join
			(select count(*) as rcnt,surrogatekey,p_no as res_p_no, 
			name as res_name, CONCAT(add1, add2, add3, city) as raddress, 
			display as res_display, lastupdatedtime as res_lastupdatedtime from ahmedabad_ssrd.respondant_t group by surrogatekey) res
			ON mt.surrogatekey = res.surrogatekey
			left join
			(select count(surrogatekey) as clscnt,surrogatekey from ahmedabad_ssrd.case_classifications group by surrogatekey) cls
			ON mt.surrogatekey = cls.surrogatekey
			left join
			(select count(lcourtid) as lccnt,surrogatekey from ahmedabad_ssrd.lower_court_mst group by surrogatekey) lc
			ON mt.surrogatekey = lc.surrogatekey
			left join
			(select count(surrogatekey) as surnocnt,surrogatekey from ahmedabad_ssrd.case_surveyno group by surrogatekey) csn
			ON mt.surrogatekey = csn.surrogatekey
			left join
			(select count(surrogatekey) as csurnocnt,surrogatekey from ahmedabad_ssrd.case_citysurveyno group by surrogatekey) ccsn
			ON mt.surrogatekey = ccsn.surrogatekey
			WHERE so.uid = 702 AND mt.case_type_code <> 1 
			AND (mt.full_case_details_entered is null or mt.full_case_details_entered = 'N')
			AND (mt.disposal_date = '0000-00-00' OR mt.disposal_date IS NULL) ORDER BY mt.registration_yr,mt.registration_no;";
	}
	else if ($dashboard_type == 'GRT') 	// Level of Office Wise e.g. 
	{
		//office_id - SLR Appellate and reply accordingly then send details of SLR Only
		$sql_summary = "SELECT so.officename,
			CONCAT(ct.edesc,' / ', dm.District_code,' / ',mt.registration_no,' / ',mt.registration_yr) AS case_number,
			CASE WHEN(cls.clscnt IS NULL OR cls.clscnt = 0) THEN 0 ELSE 1 END AS classification_count,
			CASE WHEN(mt.case_stage IS NULL OR mt.case_stage = -1 OR mt.case_stage = '') THEN 0 ELSE 1 END AS case_stage,
			CASE WHEN ((mt.type_of_matter = 'S' or mt.type_of_matter = 'T')) THEN 0 ELSE CASE WHEN (lc.lccnt = 0 OR lc.lccnt IS NULL) THEN 0 ELSE 1 END END as lower_court,
			CASE WHEN (csn.surnocnt IS NULL OR csn.surnocnt = 0) AND (ccsn.csurnocnt IS NULL OR ccsn.csurnocnt = 0) THEN 0 ELSE 1 END AS land_details,
			CASE WHEN(pet.pcnt IS NULL OR pet.pcnt = 0) THEN 0 ELSE 1 END AS petitioner_count,
			CASE WHEN(res.rcnt IS NULL OR res.rcnt = 0) THEN 0 ELSE 1 END AS respondant_count,
			CASE WHEN(mt.full_case_details_entered IS NULL OR mt.full_case_details_entered = 'N') THEN 0 ELSE 1 END AS case_details_completed
			FROM revenue_gj.sso_office_details so 
			INNER JOIN ahmedabad_grt.main_t mt ON so.uid = mt.estcode
			INNER JOIN ahmedabad_grt.case_type_t ct ON mt.case_type_code = ct.code
			INNER JOIN revenue_gj.district_mst dm ON mt.district = dm.district_id
			left join
			(select count(*) as pcnt,surrogatekey,p_no as pet_p_no, 
			name as pet_name, CONCAT(add1, add2, add3, city) as paddress, 
			display as pet_display, lastupdatedtime as pet_lastupdatedtime from ahmedabad_grt.petitioner_t group by surrogatekey) pet
			ON mt.surrogatekey = pet.surrogatekey
			left join
			(select count(*) as rcnt,surrogatekey,p_no as res_p_no, 
			name as res_name, CONCAT(add1, add2, add3, city) as raddress, 
			display as res_display, lastupdatedtime as res_lastupdatedtime from ahmedabad_grt.respondant_t group by surrogatekey) res
			ON mt.surrogatekey = res.surrogatekey
			left join
			(select count(surrogatekey) as clscnt,surrogatekey from ahmedabad_grt.case_classifications group by surrogatekey) cls
			ON mt.surrogatekey = cls.surrogatekey
			left join
			(select count(lcourtid) as lccnt,surrogatekey from ahmedabad_grt.lower_court_mst group by surrogatekey) lc
			ON mt.surrogatekey = lc.surrogatekey
			left join
			(select count(surrogatekey) as surnocnt,surrogatekey from ahmedabad_grt.case_surveyno group by surrogatekey) csn
			ON mt.surrogatekey = csn.surrogatekey
			left join
			(select count(surrogatekey) as csurnocnt,surrogatekey from ahmedabad_grt.case_citysurveyno group by surrogatekey) ccsn
			ON mt.surrogatekey = ccsn.surrogatekey
			WHERE so.uid = 701 AND mt.case_type_code <> 1 
			AND (mt.full_case_details_entered is null or mt.full_case_details_entered = 'N')
			AND (mt.disposal_date = '0000-00-00' OR mt.disposal_date IS NULL) ORDER BY mt.registration_yr,mt.registration_no;";
	}
	else 	// Level of Office Wise e.g. 
	{
		$sql_to_get_office_level = "SELECT officetype FROM revenue_gj.office_types WHERE id =:dashboard_type_code;";
		$stmt_to_get_office_level = $link_pdo->prepare($sql_to_get_office_level);
		$stmt_to_get_office_level->execute(['dashboard_type_code' => $dashboard_type_code]);
		$office_level = $stmt_to_get_office_level->fetchColumn();

		//office_id - SLR Appellate and reply accordingly then send details of SLR Only
		$sql_summary = "SELECT so.office_sname_sms, table1.total_cases FROM revenue_gj.sso_office_details so INNER JOIN
			(
			select mt.estcode,COUNT(*) AS total_cases
			from revenue_gj.main_t mt
			INNER JOIN revenue_gj.order_details od ON mt.surrogatekey = od.surrogatekey AND od.order_dt='0000-00-00'
			WHERE mt.district <> -1 
			GROUP BY mt.estcode) AS table1 ON table1.estcode = so.uid
			WHERE so.officetype = $office_level
			GROUP BY so.office_sname_sms,table1.estcode;";
	}
	// echo $sql_summary;
	// exit;
	$stmt = $link_pdo->prepare($sql_summary);

	$stmt->execute();
	$offices = array();
	while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
	{
		$offices[] = $item;
	}
	$return_json = json_encode(array("status" => "200", "message" => "OK", "data" => $offices));
	$stmt->closeCursor();
	webservice_log($link_pdo, $userid, $remote_server_ip, $requesttime, $webservicename, $params, $return_json, $date);
	return $return_json;
}