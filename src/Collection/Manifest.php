<?php

namespace AuthCRED\Collection;

trait Manifest
{
	public function asset($file)
	{
		$manifest = $this->manifest();
		$assetPath = $this->uri('dist') . '/';

		if (isset($manifest[$file])) {
			return $assetPath . $manifest[$file]['file'];
		}

		return $assetPath . $file;
	}

	public function manifest()
	{
		$manifest = [];
		$manifestPath = $this->path('dist/manifest.json');
		if (file_exists($manifestPath)) {
			$manifest = json_decode(file_get_contents($manifestPath), true);
		}
		return $manifest;
	}
	
	public function path($filePath)
	{
		global $authcred;
		return $authcred->plugin->path . $filePath;
	}

	public function uri($filePath)
	{
		global $authcred;
		return $authcred->plugin->url . '/' . $filePath;
	}
}