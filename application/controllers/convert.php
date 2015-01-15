<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Convert extends CI_Controller {

	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->db->save_queries = false;
	}

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		
		$this->load->view('welcome_message');

	}

	protected function _merge(&$obj1, $obj2) {
		foreach($obj2 as $key=>$val) {
			$obj1->{$key} = $val;
		}
	}

	protected function _extract_vals_from_row($row, &$obj) {
		unset($row->nid);
		unset($row->vid);
		foreach($row as $key=>$val) {
			$key = str_replace("field_", "", $key);
			$key = str_replace("_value", "", $key);
			$obj->{$key} = $val;
		}
	}

	protected function _find_files($row, &$obj) {
		foreach($row as $key=>$val) {
			if (strpos($key, "_fid") > -1) {
				// print "Found ".$key."\n";
				$file = $this->db->where("fid", $val)->get("prod_files")->row();
				if (isset($file->fid)) {
					$this->_merge($file, $row);

					$obj->files[] = $file;
				}
				// print_r($file);
			}
			// $key = str_replace("field_", "", $key);
			// $key = str_replace("_value", "", $key);
			// $obj->{$key} = $val;
		}
	}

	protected function _find_files_top_level(&$obj) {
		// print_r($obj);
		foreach($obj as $key=>$val) {

			if (strpos($key, "_fid") > -1) {
				// print $key;
				// print "Found ".$key."\n";
				$file = $this->db->where("fid", $val)->get("prod_files")->row();
				if (isset($file->fid)) {
					$obj->files[] = $file;
				}
			}
		}
	}

	protected function _tiny_html($html) {
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
		);

		$cwd = '/tmp';
		$env = array("word-2000" => true);

		$process = proc_open('tidy -clean -asxhtml -bare -word-2000', $descriptorspec, $pipes, $cwd, $env);

		if (is_resource($process)) {
			// $pipes now looks like this:
			// 0 => writeable handle connected to child stdin
			// 1 => readable handle connected to child stdout
			// Any error output will be appended to /tmp/error-output.txt

			fwrite($pipes[0], $html);
			fclose($pipes[0]);

			$result = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			// It is important that you close any pipes before calling
			// proc_close in order to avoid a deadlock
			$return_value = proc_close($process);

			return $result;
		}
	}

	protected function _strip_word_html($text, $allowed_tags = '<b><i><sup><sub><em><strong><u><br><p>')
    {
    	// if (strlen($text) > 100000) {
    	// 	return "Too big to process";
    	// }
        mb_regex_encoding('UTF-8');
        //replace MS special characters first
        $search = array('/&lsquo;/u', '/&rsquo;/u', '/&ldquo;/u', '/&rdquo;/u', '/&mdash;/u');
        $replace = array('\'', '\'', '"', '"', '-');
        $text = preg_replace($search, $replace, $text);
        //make sure _all_ html entities are converted to the plain ascii equivalents - it appears
        //in some MS headers, some html entities are encoded and some aren't
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        //try to strip out any C style comments first, since these, embedded in html comments, seem to
        //prevent strip_tags from removing html comments (MS Word introduced combination)
        if(mb_stripos($text, '/*') !== FALSE){
            $text = mb_eregi_replace('#/\*.*?\*/#s', '', $text, 'm');
        }
        $text = str_replace( chr( 194 ) . chr( 160 ), ' ', $text );
        //introduce a space into any arithmetic expressions that could be caught by strip_tags so that they won't be
        //'<1' becomes '< 1'(note: somewhat application specific)
        $text = preg_replace(array('/<([0-9]+)/'), array('< $1'), $text);
        $text = strip_tags($text, $allowed_tags);
        //eliminate extraneous whitespace from start and end of line, or anywhere there are two or more spaces, convert it to one
        $text = preg_replace(array('/^\s\s+/', '/\s\s+$/', '/\s\s+/u'), array('', '', ' '), $text);
        //strip out inline css and simplify style tags
        
        //on some of the ?newer MS Word exports, where you get conditionals of the form 'if gte mso 9', etc., it appears
        //that whatever is in one of the html comments prevents strip_tags from eradicating the html comment that contains
        //some MS Style Definitions - this last bit gets rid of any leftover comments */
        // $num_matches = preg_match_all("/\<!--/u", $text, $matches);
        // if($num_matches){
              
        // }
        $text = preg_replace('/<p.*?>(.*?)<\/p>/isu', '<p>$1</p>', $text);

        $text = preg_replace(':<[^/>]*>\s*</[^>]*>:', '', $text);

        $search = array('#<(strong|b )[^>]*>(.*?)</(strong|b)>#isu', '#<(em|i)[^>]*>(.*?)</(em|i)>#isu', '#<u[^>]*>(.*?)</u>#isu');
        $replace = array('<strong>$2</strong>', '<i>$2</i>', '<u>$1</u>');
        $text = preg_replace($search, $replace, $text);
        $text = preg_replace('/<!--(.*?)-->/isu', '', $text);
        $text = preg_replace('/<br(.*?)\/>/isu', '<br/>', $text);
        return $text;
    }

	protected function _clean_html($html) {
		$comments = "/<!--(.*)-->/Uis";
		$lang = '/lang="EN-GB"/';
		$msonormal = '/class="MsoNormal"/';
		$center = '/align="center"/';
		$styles = '/\sstyle=/g';
		// $tabstops = '/tab-stops:(.*)[;"\']/U';
		// $fontsize = '/font-size:(.*)[;"\']/U';
		// $lineheight = '/line-height:(.*)[;"\']/U';
		// $fontfamily = '/font-family:(.*)["|;|\']/Us';
		// $textalign = '/text-align:(.*)[;"\']/U';
		$emptyp = '/<p><\/p>/';
		$replace = array($comments, $emptyp, $lang, $msonormal, $center);
		$html = preg_replace($replace, "", $html);
		return $html;
	}

	protected function _content_type($type, $limit=10, $offset=0, $write_to_db = false) {
		$docs = array();
		$fname = FCPATH . "data/" . $type . ".json";
		// print $fname;
		// die();
		if ($write_to_db) {
			$f = fopen($fname, "a");
		}
		//First fetch all the nodes
		$nodequery = $this->db->where("type", $type)->limit($limit)->offset($offset)->group_by("nid")->get("prod_node");
		foreach($nodequery->result() as $node) {
			$doc = new stdClass;
			$doc->nid = $node->nid;
			$doc->vid = $node->vid;
			$doc->title = $node->title;
			$doc->content_type = $type;
			$doc->start_date = $node->created;
			$doc->terms = array();
			$doc->audio = array();
			$doc->files = array();
			$doc->revisions = array();
			
			if ($this->db->table_exists("prod_content_type_{$type}")) {
				$tmp = $this->db->where("vid", $doc->vid)->get("prod_content_type_{$type}")->row();
				$this->_extract_vals_from_row($tmp, $doc);
			}

			$fields = $this->db->where("type_name", $type)->get("prod_content_node_field_instance")->result();
			foreach($fields as $field) {
				$tname = "prod_content_".$field->field_name;
				if ($this->db->table_exists($tname)) {
					$tmp = $this->db->where("vid", $doc->vid)->get($tname)->result();
					foreach($tmp as $fieldrow) {
						// print_r($fieldrow);
						$this->_extract_vals_from_row($fieldrow, $doc);
						$this->_find_files($fieldrow, $doc);
					}
				}
			}
			
			// Terms - used to link reports to committees and something to bills
			$terms = $this->db->where("prod_term_node.vid", $doc->vid)->join("prod_term_data", "prod_term_data.tid = prod_term_node.tid")->get("prod_term_node")->result();
			foreach($terms as $term) {
				$doc->terms[] = $term->name;
			}

			$audios = $this->db->where("vid", $doc->vid)->get("prod_content_field_audio_reference")->result();
			foreach($audios as $audio) {
				if ($audio->field_audio_reference_nid) {
					// print_r($audio);
					$audio_dbs = $this->db->where("nid", $audio->field_audio_reference_nid)->join("prod_files", "prod_files.fid = prod_audio.fid")->get("prod_audio")->result();
					// print_r($audio_dbs);
					foreach($audio_dbs as $audio_db) {
						$audio_db->pmg_filename = "http://pmg.org.za/audio/download/".$audio_db->nid."/".$audio_db->filename;
						$doc->audio[] = $audio_db;

					}
				}
			}

			$this->_find_files_top_level($doc);

			$revision = $this->db->where("vid", $doc->vid)->get("prod_node_revisions")->row();
			if (!empty($revision->body)) {
				$doc->body = $this->_strip_word_html($revision->body);
			}
			if (!empty($revision->teaser)) {
				$doc->teaser = trim(trim(html_entity_decode(strip_tags($revision->teaser)), chr(0xC2).chr(0xA0)));
			}
			if (!empty($revision->title)) {
				$doc->title = $revision->title;
			}
			$doc->revision_timestamp = $revision->timestamp;
			// foreach($revisions as $revision) {
				// $revision->body = $this->_strip_word_html($revision->body);
				// $revision->teaser = strip_tags($revision->teaser);
				// $doc->revisions[] = $revision;
			// }
			if ($write_to_db) {
				// $this->mongo_db->insert("pmg_".$type, $doc);
				fwrite($f, json_encode($doc));
				fwrite($f, "\n");
				$docs[] = $doc->nid;
			} else {
				$docs[] = $doc;	
			}

			
		}
		fclose($f);
		return $docs;
	}

	protected function _users($limit, $offset, $write_to_db = false, $delete_db = true) {
		$result = array();
		
		$fname = FCPATH . "data/users.json";
		// print $fname;
		// die();
		if ($write_to_db) {
			$f = fopen($fname, "a");
		}
		$users = $this->db->select("uid, name, pass, mail, created, access, login, status, timezone, language, init, timezone_name")->limit($limit)->offset($offset)->get("prod_users");

		foreach($users->result() as $user) {
			$terms = $this->db->select("prod_term_data.name, prod_term_data.tid")->select("0 AS premium", false)->where("uid", $user->uid)->join("prod_term_data", "prod_term_data.tid = prod_users_terms_alerts.tid")->get("prod_users_terms_alerts");
			$premium = $this->db->select("prod_term_data.name, prod_term_data.tid")->select("1 AS premium", false)->where("uid", $user->uid)->join("prod_term_data", "prod_term_data.tid = prod_users_terms_premium.tid")->get("prod_users_terms_premium");
			$user->subscribed = array_merge($terms->result(), $premium->result());
			$terms->free_result();
			$premium->free_result();
			// unset($user->data);
			if ($write_to_db) {
				fwrite($f, json_encode($user));
				fwrite($f, "\n");
			} else {
				$result[] = $user;
			}
			
			// $result[]=$user;
		}
		$users->free_result();
		if (!$write_to_db) {
			return $result;
		} else {
			return "All done";
		}
	}

	public function content_type($type, $limit=10, $offset=0) {
		header("Content-Type: text/json");
		print json_encode($this->_content_type($type, $limit, $offset));
	}

	public function dump($type, $limit=100000000, $offset=0, $mongo_save=true) {
		header("Content-Type: text/json");
		print json_encode($this->_content_type($type, $limit, $offset, $mongo_save));
	}

	public function dump_all() {
		$per_run = 100;
		
		$types = $this->db->get("prod_node_type")->result();
		print "<h1>Starting Dump</h1>";
		foreach($types as $type) {
			print "<strong>Generating type ".$type->name."</strong><br />";
			// $this->mongo_db->delete_all("pmg_".$type->type);
			$item_count = $this->db->where("type", $type->type)->count_all_results("prod_node");
			$runs = ceil($item_count / $per_run);
			print "Number of items: $item_count<br /> Number of runs: $runs<br />";
			flush();
			for ($run = 0; $run < $runs; $run++) {
				set_time_limit(60);
				$docs = $this->_content_type($type->type, $per_run, $run * $per_run, true);
				// foreach ($docs as $doc) {
				// 	$this->mongo_db->insert("pmg_".$type->type, $doc);
				// }
				echo ". ";
				flush();
			}
			print "<br/><br/>";
		}
	}

	public function export_all() {
		$types = $this->db->get("prod_node_type")->result();
		foreach($types as $type) {
			// print "<strong>Generating type ".$type->name."</strong><br />";
			$s = "/usr/local/bin/mongoexport -d pmg -c pmg_".$type->type." -o /Users/jason/Sites/pmg.dev/dump/".$type->type.".json";
			print "$s<br>";
			// print system($s);
		}
	}

	public function users($limit=10, $offset=0, $mongo_save = false) {
		header("Content-Type: text/json");
		print json_encode($this->_users($limit, $offset, $mongo_save));
	}

	public function all_users() {
		// $this->mongo_db->delete_all("pmg_users");
		$total = 25089; //Cheating
		$per_request = 1000;
		for ($x = 0; $x < ceil($total / $per_request); $x++) {
			print "Offset " + ($x * $per_request)+"<br>\n";
			$this->_users($per_request, $x * $per_request, true, false);
		}
		print "All done";
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */