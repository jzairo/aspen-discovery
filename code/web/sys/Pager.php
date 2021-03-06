<?php

/**
 * Pager Class
 *
 * Creates pagination links for search results and other locations
 *
 */
class Pager {
	private $options;
	private $_currentPage;
	private $_totalPages;
	private $_baseUrl;

	/**
	 * Constructor
	 *
	 * Initialize the PEAR pager object.
	 *
	 * @param   array $options        The Pager options to override.
	 * @access  public
	 */
	public function __construct($options = array()) {
		// Set default Pager options:
		$finalOptions = array(
			'perPage' => 20,
			'urlVar' => 'page',
			'totalItems' => 0
		);

		// Override defaults with user-provided values:
		foreach ($options as $optionName => $optionValue) {
			$finalOptions[$optionName] = $optionValue;
		}
		$this->options = $finalOptions;

		if (isset($this->options))

		$urlVar = $this->options['urlVar'];
		$this->_currentPage = (isset($_REQUEST[$urlVar]) && is_numeric($_REQUEST[$urlVar])) ? $_REQUEST[$urlVar] : 1;
        $this->_totalPages = ceil($this->options['totalItems'] / $this->options['perPage']);

        $this->_baseUrl = $this->curPageURL();
        //remove the page parameter
        $this->_baseUrl = preg_replace("/\?{$this->options['urlVar']}=\d+&/",'?', $this->_baseUrl);
        $this->_baseUrl = preg_replace("/{$this->options['urlVar']}=\d+&|[?&]{$this->options['urlVar']}=\d+/",'', $this->_baseUrl);
	}

    private function curPageURL() {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }


    /**
	 * Generate the pager HTML using the options passed to the constructor.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getLinks() {
	    $links = array();
        if ($this->_totalPages > 1) {
            //$links['all'] = 'Pagination goes here.  On page ' . $this->getCurrentPage() . ' of ' . $this->getTotalPages();
            $linksText = '<nav aria-label="Page navigation">';
            $linksText .= '<ul class="pagination justify-content-end">';
            if ($this->getCurrentPage() != 1) {
                $linksText .=  '<li class="page-item">' . $this->renderLink(1) . '[1]</a></li>';
                $linksText .=  '<li class="page-item">' . $this->renderLink(1) . '&laquo; ' . translate('Prev') . '</a></li>';
            }

            //Print links to pages before and after the current
            $firstPageToPrint = $this->_currentPage - 2;
            $lastPageToPrint = $this->_currentPage + 2;
            if ($firstPageToPrint < 1) {
                $lastPageToPrint -= ($firstPageToPrint - 1);
                $firstPageToPrint = 1;
            }
            if ($lastPageToPrint > $this->_totalPages){
                $firstPageToPrint -= $lastPageToPrint - $this->_totalPages;
                $lastPageToPrint = $this->_totalPages;
            }
            if ($firstPageToPrint < 1) {
                $firstPageToPrint = 1;
            }
            if ($lastPageToPrint > $this->_totalPages) {
                $lastPageToPrint = $this->_totalPages;
            }
            for ($i = $firstPageToPrint; $i <= $lastPageToPrint; $i++) {
                $active = ($this->_currentPage == $i) ? ' active' : '';
                $linksText .=  '<li class="page-item' . $active . '">' . $this->renderLink($i) . "$i</a></li>";
            }
            if ($this->_currentPage != $this->_totalPages) {
                $linksText .=  '<li class="page-item">' .  $this->renderLink($this->_currentPage + 1) . translate('Next') . ' &raquo;</a></li>';
                $linksText .=  '<li class="page-item">' . $this->renderLink($this->getTotalPages()) . '['.$this->getTotalPages().']</a></li>';
            }
            $linksText .= '</ul>';
            $linksText .= '</nav>';
            $links['all'] = $linksText;  
        } else {
            $links['all'] = null;
        }
        return $links;
	}

	public function renderLink($pageNumber){
		if (empty($this->options['linkRenderingFunction'])){
			return '<a class="page-link" href="' .  $this->getPageUrl($pageNumber) . '">';
		}else{
			$object = $this->options['linkRenderingObject'];
			$function = $this->options['linkRenderingFunction'];
			return $object->$function($pageNumber, $this->options);
		}
	}

	public function isLastPage() {
	    $currentPage = $this->_currentPage;
		$totalPages = $this->_totalPages;
		return $currentPage == $totalPages;
	}

	public function getNumRecordsOnPage() {
	    if (!$this->isLastPage()) {
			return $this->getItemsPerPage();
		}
		return $this->getTotalItems() - ($this->getItemsPerPage() * ($this->getCurrentPage() - 1));
	}

	public function getCurrentPage(){
	    return $this->_currentPage;
    }

    public function getTotalPages(){
	    return $this->_totalPages;
    }

    public function getTotalItems(){
	    return $this->options['totalItems'];
    }

    public function getItemsPerPage(){
	    return $this->options['perPage'];
    }

    public function getPageUrl($page) {
		if (strpos($this->_baseUrl, '?') > 0) {
			$url = $this->_baseUrl . '&' . $this->options['urlVar'] . '=' . $page;
		} else {
			$url = $this->_baseUrl . '?' . $this->options['urlVar'] . '=' . $page;
		}

	    return $url;
    }

}
