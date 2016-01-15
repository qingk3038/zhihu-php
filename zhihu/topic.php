<?php

class Topic 
{
	public $url;
	private $name;

	function __construct($url, $name = null)
	{
		if (substr($url, 0, 28) != TOPICS_PREFIX_URL) {
			throw new Exception($url.": it isn't a topics url !");
		} else {
			$this->url = $url;
			if ( ! empty($name)) {
				$this->name = $name;
			}
		}
	}

	/**
	 * 解析话题主页
	 * @return object simple html dom 对象
	 */
	public function parser()
	{
		if (empty($this->dom) || ! isset($this->dom)) {
			$r = Request::get($this->url);

			$this->dom = str_get_html($r);
		}
	}

	/**
	 * 获取该话题的名称
	 * @return string 话题名称
	 */
	public function name()
	{
		if ( ! empty($this->name)) {
			return $this->name;
		} else {
			$this->parser();
			$this->name = $this->dom->find('h1', 0)->plaintext;
			return $this->name;
		}
	}

	/**
	 * 获取话题描述
	 * @return string 话题描述
	 */
	public function description()
	{
		$this->parser();
		$description = trim($this->dom->find('div#zh-topic-desc div.zm-editable-content', 0)->plaintext);
		if ($description === '') {
			$description = null;
		}
		return $description;
	}


	/**
	 * 获取关注该话题的人数
	 * @return integer 话题关注人数
	 */
	public function followers()
	{
		$this->parser();
		$followers_num = (int)$this->dom->find('div.zm-topic-side-followers-info strong', 0)->plaintext;
		return $followers_num;
	}

	/**
	 * 获取该话题的父话题
	 * @return array 父话题列表
	 */
	public function parent()
	{
		$this->parser();
		$parent_link = $this->dom->find('div.parent-topic', 0);

		for ($i = 0; ! empty($parent_link->find('a', $i)) ; $i++) { 
			$parent_url = ZHIHU_URL.$parent_link->find('a', $i)->href;
			$parent_id = $parent_link->find('a', $i)->plaintext;
			$parent_list[] = new Topic($parent_url, $parent_id);
		}
		return $parent_list;
	}

	/**
	 * 获取该话题的子话题
	 * @return array 子话题列表
	 */
	public function children()
	{
		if (empty($this->dom->find('a.zm-topic-side-title-link', 0))) {
			return null;
		}
		$children_num = $this->dom->find('a.zm-topic-side-title-link', 0)->plaintext;
		$children_num = (int)explode(' ', $children_num, 3)[1];

		$entire_url = $this->url.'/organize';
		$r = Request::get($entire_url);
		$dom = str_get_html($r);

		for ($i = 0; $i < $children_num; $i++) { 
				$children_link = $dom->find('div#zh-topic-organize-child-editor a', $i);

				$children_url = ZHIHU_URL.substr($children_link->href, 0, 15);
				$children_id = trim($children_link->plaintext);
				$children_list[] = new Topic($children_url, $children_id);
		}
		return $children_list;
	}

	/**
	 * 获取该话题下的最佳回答者
	 * @return array 最佳回答者列表
	 */
	public function answerer()
	{
		$this->parser();
		for ($i = 0; ! empty($this->dom->find('div.zm-topic-side-person-item', $i)) ; $i++) { 
			$answerer_link = $this->dom->find('div.zm-topic-side-person-item', $i)->find('a', 1);
			$answerer_url = ZHIHU_URL.$answerer_link->href;
			$answerer_id = trim($answerer_link->plaintext);
			$answerer_list[] = new User($answerer_url, $answerer_id);
		}
		return $answerer_list;
	}

	/**
	 * 获取该话题下热门问题
	 * @return Generator 热门问题列表
	 */
	public function hot_question()
	{
		$hot_question_url = $this->url.TOPICS_HOT_SUFFIX_URL;
		return $this->question($hot_question_url);
	}

	/**
	 * 获取该话题下最新问题
	 * @return Generator 最新问题列表
	 */
	public function new_question()
	{
		$new_question_url = $this->url.TOPICS_NEW_SUFFIX_URL;
		return $this->question($new_question_url);
	}

		/**
	 * 获取该话题下全部问题
	 * @return Generator 全部的问题
	 */
	public function all_question()
	{
		$all_question_url = $this->url.TOPICS_ALL_SUFFIX_URL;
		$r = Request::get($all_question_url);
		$dom = str_get_html($r);
		$max_page = (int)$dom->find('div.zm-invite-pager span', -2)->plaintext;

		for ($i = 1; $i <= $max_page; $i++) { 
			if ($i > 1) {
				$page_url = $all_question_url.GET_PAGE_SUFFIX_URL.$i;
				$r = Request::get($page_url);
				$dom = str_get_html($r);
			}

			for ($j = 0; ! empty($dom->find('h2 a.question_link', $j)); $j++) { 
				$question_link = $dom->find('h2 a.question_link', $j);
				$question_url = ZHIHU_URL.$question_link->href;
				$question_title = $question_link->plaintext;
				yield new Question($question_url, $question_title);	
			}
		}
	}


	/**
	 * 获取问题列表
	 * @param  string $url 目标 url
	 * @return Generator   问题迭代器
	 */		
	private function question($url)
	{
		$r = Request::get($url);
		$dom = str_get_html($r);

		$tmp_url = null;
		$_xsrf = $dom->find('input[name=_xsrf]', 0)->attr['value'];
		for ($i = 0; ! empty($dom->find('div.feed-item', $i)); $i++) { 
			$combine = $dom->find('div.feed-item', $i);
			$offset = $combine->attr['data-score'];

			$question_link = $combine->find('h2 a.question_link', 0);
			$question_url = ZHIHU_URL.$question_link->href;

			if ($question_url != $tmp_url) {
				$tmp_url = $question_url;

				$question_title = $question_link->plaintext;
				yield new Question($question_url, $question_title);
			}
		}
		do {
			$data = array(
				'start' => 0,
				'offset' => $offset,
				'_xsrf' => $_xsrf
			);
			$r = Request::post($url, $data, array('Referer: {$url}"'));
			$r = json_decode($r)->msg;
			$item = $r[0];
			$dom = str_get_html($r[1]);

			for ($i = 0; $item && ! empty($dom->find('div.feed-item', $i)) ; $i++) { 
				$combine = $dom->find('div.feed-item', $i);
				$offset = $combine->attr['data-score'];

				$question_link = $combine->find('h2 a.question_link', 0);
				$question_url = ZHIHU_URL.$question_link->href;

				if ($question_url != $tmp_url) {
					$tmp_url = $question_url;

					$question_title = $question_link->plaintext;
					yield new Question($question_url, $question_title);
				}
			}
		} while ($item);
	}


	/**
	 * 获取该话题下精华回答
	 * @return Generator 精华回答列表
	 */
	public function top_answer()
	{
		$top_answer_url = $this->url.TOPICS_TOP_SUFFIX_URL;
		$r = Request::get($top_answer_url);
		$dom = str_get_html($r);
		$max_page = (int)$dom->find('div.zm-invite-pager span', -2)->plaintext;

		for ($i = 1; $i <= $max_page; $i++) { 
			if ($i > 1) {
				$page_url = $top_answer_url.GET_PAGE_SUFFIX_URL.$i;
				$r = Request::get($page_url);
				$dom = str_get_html($r);
			}

			for ($j = 0; ! empty($dom->find('div.feed-item', $j)); $j++) { 
				$item = $dom->find('div.feed-item', $j);
				$answer_url = ZHIHU_URL.$item->find('div.zm-item-rich-text', 0)->attr['data-entry-url'];

				$question_link = $item->find('h2 a.question_link', 0);
				$question_url = ZHIHU_URL.$question_link->href;
				$question_title = $question_link->plaintext;
				$question =  new Question($question_url, $question_title);

				$author_link = $item->find('div.zm-item-answer-author-info a', 0);
				$author_url = ZHIHU_URL.$author_link->href;
				$author_name = trim($author_link->plaintext);
				$author = new User($author_url, $author_name);

				$upvote = $item->find('span.count', 0)->plaintext;

				$content = $item->find('textarea.content', 0)->plaintext;

				yield new Answer($answer_url, $question, $author, $upvote, $content);	
			}
		}
	}
}