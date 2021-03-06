<?php

class Sound {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isExist($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            return $this->redis->exists('sound.' . $sound_id);
        }
        
        return false;
    }

    public function get($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $column = ['id', 'name', 'file', 'company', 'remark', 'status', 'operator', 'create_time', 'ip_addr'];
            return $this->redis->hMGet('sound.' . $sound_id, $column);
        }

        return null;
    }

    public function create($uid = null, $company_id = null, $data = null, $files = null) {
        $company_id = intval($company_id);
        $data = $this->checkUpload($data, $files);

        if ($company_id > 0 && $data != null && $files != null) {
            $file = uniqid().'.wav';
            $path = '/var/www/lemon/public/sounds/' . $file;
            if (move_uploaded_file($data['tmpFile'], $path)) {
                $data['file'] = $file;
                $data['company'] = $company_id;
                $data['duration'] = 0;
                $data['operator'] = $uid;
                $data['create_time'] = date('Y-m-d H:i:s', time());
                $data['ip_addr'] = $_SERVER["REMOTE_ADDR"];

                $sound_check = $this->redis->hGet('company.' . $company_id, 'sound_check');
                if ($sound_check === '0') {
                    $data['status'] = 1;
                } else {
                    $data['status'] = 0;
                }

                unset($data['tmpFile']);
                $success = $this->db->insertAsDict('sounds', $data);
                if ($success) {
                    $sql = "SELECT last_value FROM sounds_id_seq WHERE sequence_name = 'sounds_id_seq'";
                    $result = $this->db->fetchOne($sql);
                    if ($result) {
                        $sound_id = intval($result['last_value']);
                        if ($sound_id > 0) {
                            $this->redis->hMSet('sound.' . $sound_id, $data);
                        }
                    }
                    return true;
                }
                unlink($path);
            }
        }

        return false;
    }

    public function update($sound_id = null, $data = null) {
        $sound_id = intval($sound_id);
        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $data = $this->filter($data);
            if ($data != null) {
                $success = $this->db->updateAsDict('sounds', $data, 'id = ' . $sound_id);
                if ($success) {
                    $this->redis->hMSet('sound.' . $sound_id, $data);
                    return true;
                }
            }
        }

        return false;
    }

    public function checkUpload($data = null, $files = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            } else {
                return null;
            }
        } else {
            return null;
        }

        // check upload file
        if (!isset($files['file']) && !is_array($files['file'])) {
            return null;
        }

        if ($files['file']['error'] !== 0) {
            return null;
        }
    
        $file = $files['file']['tmp_name'];
        $finfo = finfo_open (FILEINFO_MIME);
        if (!$finfo) {
            return null;
        }

        if (finfo_file($finfo, $file) !== 'audio/x-wav; charset=binary') {
            return null;
        }

        finfo_close($finfo);
    
        $size = $files['file']['size'];
        if ($size > 0 && $size < 2097152) {
            $buff['tmpFile'] = $files['file']['tmp_name'];
        } else {
            return null;
        }

        if (isset($data['remark'])) {
            $remark = str_replace(" ", "", $this->filter->sanitize($data['remark'], 'string'));
            $len = mb_strlen($remark, 'utf-8');
            if ($len > 0) {
                $buff['remark'] = htmlspecialchars($remark, ENT_QUOTES);
            } else {
                $buff['remark'] = 'no description';
            }
        } else {
            $buff['remark'] = 'no description';
        }

        return $buff;
    }

    public function filter($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['remark'])) {
            $remark = str_replace(" ", "", $this->filter->sanitize($data['remark'], 'string'));
            $len = mb_strlen($remark, 'utf-8');
            if ($len > 0) {
                $buff['remark'] = htmlspecialchars($remark, ENT_QUOTES);
            }
        }

        return $buff;
    }
}
