<?php

namespace App\Http\Supports;

trait RepositoryUtil
{
	/**  */
	protected $_repos = [];

	public function registerRepository(...$repo)
	{
		foreach ($repo as $value) {
			$this->_repos[ get_class($value) ] = $value;
		}
		return $this;
	}

	/** 
	 * @param string $key
	 * @return CustomRepository
	 */
	public function fetchRepository($key)
	{
        if ($key) {
            return array_get($this->_repos, $key);
		}
		return null;
	}

	public function fetchModel($className)
	{
		return $this->fetchRepository($className)->getModel();
	}
}