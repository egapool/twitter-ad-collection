<?php

Class PromoteTweet
{
	public $dbh = null;

	public function __construct($host, $dbname,$user,$password)
	{
		try {
			$pdo = new PDO("mysql:host=" . $host . ";dbname=" . $dbname,$user, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$this->dbh = $pdo;
		} catch(PDOException $e) {
			echo $e->getMessage();
			die;
	    }
	}

	/**
	 * insert into db promoted account.
	 *
	 */
	public function registerAccount($screen_name,$name, $status, $icon)
	{
		$havingAccounts = $this->fetchPromotedAccount();

		if ( !isset($havingAccounts[$screen_name]) ) {
			$sql = "INSERT INTO `ad_accounts` (screen_name,name,icon,ad_status,created_at,updated_at) VALUES (:screen_name,:name,:icon,:status,:now,:now);";
			$sth = $this->dbh->prepare($sql);
			$sth->bindParam(':screen_name', $screen_name, PDO::PARAM_STR);
			$sth->bindParam(':name', $name, PDO::PARAM_STR);
			$sth->bindParam(':status', $status, PDO::PARAM_STR);
			$sth->bindParam(':icon', $icon, PDO::PARAM_STR);
			$sth->bindValue(':now', time(), PDO::PARAM_INT);
			$sth->execute();
			return true;
		}

		return false;
	}


	public function fetchPromotedAccount()
	{
		$output = [];
		$sql = "SELECT screen_name FROM `ad_accounts`";
		$sth = $this->dbh->query($sql);
		$accounts = $sth->fetchAll(PDO::FETCH_ASSOC);
		foreach ( $accounts as $val ) {
			$output[$val['screen_name']] = '';
		}
		return $output;
	}
}