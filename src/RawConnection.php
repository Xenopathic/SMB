<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

class RawConnection {
	/**
	 * @var resource[] $pipes
	 *
	 * $pipes[0] holds STDIN for smbclient
	 * $pipes[1] holds STDOUT for smbclient
	 */
	private $pipes;

	/**
	 * @var resource $process
	 */
	private $process;


	public function __construct($command, $env = array()) {
		$descriptorSpec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array('file', '/dev/null', 'w')
		);
		setlocale(LC_ALL, Server::LOCALE);
		$env = array_merge($env, array(
			'CLI_FORCE_INTERACTIVE' => 'y', // Needed or the prompt isn't displayed!!
			'LC_ALL' => Server::LOCALE
		));
		$this->process = proc_open($command, $descriptorSpec, $this->pipes, null, $env);
		if (!$this->isValid()) {
			throw new ConnectionError();
		}
	}

	/**
	 * check if the connection is still active
	 *
	 * @return bool
	 */
	public function isValid() {
		if (is_resource($this->process)) {
			$status = proc_get_status($this->process);
			return $status['running'];
		} else {
			return false;
		}
	}

	/**
	 * send input to the process
	 *
	 * @param string $input
	 */
	public function write($input) {
		fwrite($this->pipes[0], $input);
		fflush($this->pipes[0]);
	}

	/**
	 * read a line of output
	 *
	 * @return string
	 */
	public function read() {
		return trim(fgets($this->pipes[1]));
	}

	/**
	 * get all output until the process closes
	 *
	 * @return array
	 */
	public function readAll() {
		$output = array();
		while ($line = $this->read()) {
			$output[] = $line;
		}
		return $output;
	}

	public function getOutputStream() {
		return $this->pipes[1];
	}

	public function getInputStream() {
		return $this->pipes[0];
	}

	public function __destruct() {
		proc_terminate($this->process);
		proc_close($this->process);
	}
}
