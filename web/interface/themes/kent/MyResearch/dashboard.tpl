{css media="screen" filename="dashboard.css"}
<div id="bd">
  <div id="yui-main" class="content">
    <b class="btop"><b></b></b>
<div id="content" class='dashboard'>
	<div class="box">
		<div class="heading">
		    <h3 class="items"><a href="{$path}/MyResearch/CheckedOut">{translate text='Items due back soon'}</a>{if (is_array($transList) && count($transList))}<a href="{$path}/MyResearch/CheckedOut" class='more'>{translate text="view all items"}</a>{/if}</h3>
		    
		</div>
		{assign var=iteration value=0}
		{if (is_array($transList) && count($transList))}
		    <ul class="items">
		    	{foreach from=$transList item=resource name="recordLoop"}
		    		{if $iteration++}{/if}
				{if $iteration<=5}
		    		<li class="clearfix {$format|lower|regex_replace:"/[^a-z0-9]/":""} {if $iteration%2==0}color{/if} {if $resource.urgancy == 2}red special{elseif $resource.urgancy == 1}special yellow{/if} {if $smarty.foreach.recordLoop.last}last{/if}">
		    			<img src="/interface/themes/kent/images/dashboard/ico-{$resource.format.0|lower|regex_replace:"/[^a-z0-9]/":""}.png" width="16" height="16" alt="{translate text=$format}" class="png format" /> 
		    			<div class='content'>
		    				<a class="post-heading" href="{$path}/Record/{$resource.id|escape:"url"}">{$resource.title|escape|trim:' /: '}</a>
		    				<em class="date"><strong>{$resource.status|escape|translate}{if $resource.status!=""},  {translate text='due'}{else}{translate text='Due'}{/if}:</strong> {$resource.duedate|date_format:"%e %b %Y (%H:%M)"}</em>
		    				<ul class="action-list">
		    					<li class="status">{$resource.dueText|escape|translate} <strong>{$resource.dueInText|escape|translate}</strong></li>
		    					{if $resource.ils_details.renewable}
		    						<li><a class="renew" href="{$path}/MyResearch/Dashboard?renewSelected=renewSelected&renewSelectedIDS[]={$resource.ils_details.renew_details}">renew</a></li>
		    					{/if}
		    				</ul>
		    			</div>
		    		</li>
				{/if}
		    	{/foreach}
		    </ul>
		    <div class="bottom-row-holder">
		    	<div class="bottom-row clearfix">
		    		<a href="{$path}/MyResearch/CheckedOut" class='more'>{translate text="view all items"}</a>
		    		{if $transLeft>=1}
		    			<p>and {$transLeft} other{if $transLeft>1}s{/if}</p>
		    		{/if}
		    	</div>
		    </div>
		{else}
		    <div class="box-holder">
		    	<strong class="empty-text">
		    		{translate text='You do not have any items checked out'}.      
		        </strong>
		    </div>
		{/if}
		
	</div>
	<div class="box">
	    <div class="heading">
	    	<h3 class="searches"><a href="{$path}/MyResearch/Saved">{translate text="Saved Searches"}</a></h3>
	    </div>
	    <div class="row-holder">
			{assign var=iteration value=0}
	    	{if !$noSavedSearches}
	    		{foreach item=info from=$saved name="historyLoop"}
	    			{if $iteration++}{/if}
				{if $iteration<=5}
	     			<div class="row {if $iteration%2==0}color{/if} ">
				        <ul class="action">
	  				    	<li><a class="rerun" href="{$info.url|escape}">{translate text='Rerun'}</a></li>
	  				    	<li><a class="delete" href="{$path}/MyResearch/SaveSearch?delete={$info.searchId|escape:"url"}&amp;mode=saved" class="delete">{translate text="history_delete_link"}</a></li>
	    			    </ul>
	    			    <span class="type">
	  				    	<a href="{$info.url|escape}">
	  				    		<strong>{if empty($info.description)}{translate text="history_empty_search"}{else}{$info.description|escape}{/if}</strong>
  					    		{foreach from=$info.filters item=filters key=field}
  					    			{foreach from=$filters item=filter}
				     	     			and {translate text=$field|escape}: <strong>{$filter.display|escape}</strong>
	       			    			{/foreach}
         			    		{/foreach}
          			    	</a>
					    </span>
	    			    <span class="result">{$info.time}, {$info.hits} results</span>
				    </div>
				    {/if}
				{/foreach}
			{else}
				<div class="box-holder">
					<strong class="empty-text">{translate text="You don’t have any saved searches"}</strong>
					<p>{translate text="You can save searches from the search results page or your"} <a href="{$path}/Search/History">{translate text="Search History"}</a></p>
				</div>
			{/if}
	    </div>
		<div class="bottom-row-holder">
	    	<div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Saved" class='more'>{translate text="view all"}</a>
	    		{if $savedSearchesLeft}
			    	<p>and {$savedSearchesLeft} other{if $savedSearchesLeft>1}s{/if} </p>
			    {/if}
		    </div>
		</div>
	</div>
	<div class="box">
	    <div class="heading">
	    	<h3 class="history"><a href="{$path}/Search/History">{translate text="Search History"}</a></h3>
	    </div>
	    <div class="row-holder">
	    	{assign var=iteration value=0}
	    	{if !$noHistorySearches}
	    		{foreach item=info from=$history name="searchhistoryLoop"}
	    			{if $iteration++}{/if}
				{if $iteration<=5}
	     			<div class="row {if $iteration%2==0}color{/if} ">
				        <ul class="action">
	  				    	<li><a class="rerun" href="{$info.url|escape}">{translate text='Rerun'}</a></li>
	  						<li><a class="save1" href="{$path}/MyResearch/SaveSearch?save={$info.searchId|escape:"url"}&amp;mode=history" class="add">{translate text="history_save_link"}</a></li>
	    			    </ul>
	    			    <span class="type">
	  				    	<a href="{$info.url|escape}">
	  				    		<strong>{if empty($info.description)}{translate text="history_empty_search"}{else}{$info.description|escape}{/if}</strong>
  					    		{foreach from=$info.filters item=filters key=field}
  					    			{foreach from=$filters item=filter}
				     	     			and {translate text=$field|escape}: <strong>{$filter.display|escape}</strong>
	       			    			{/foreach}
         			    		{/foreach}
          			    	</a>
					    </span>
	    			    <span class="result">{$info.time}, {$info.hits} results</span>
				    </div>
				{/if}
				{/foreach}
			{else}
				<div class="box-holder">
					<strong class="empty-text">{translate text="You haven’t performed any searches"}</strong>
					<p>{translate text="Search history is not saved across visits<br /> if you need your searches across visits please save them"}</p>
				</div>			
			{/if}
	    </div>
	    <div class="bottom-row-holder">
	    	<div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Saved" class='more'>{translate text="view all"}</a>
	    		{if $historySearchesLeft}
			    	<p>and {$historySearchesLeft} other{if $historySearchesLeft>1}s{/if} </p>
			    {/if}
		    </div>
		</div>
	</div>
	<div class="box">
	    <div class="heading">
	    	<h3 class="profile"><a href="{$path}/MyResearch/Profile">{translate text="Profile"}</a></h3>
	    </div>
	    <ul class="profile-list">
	    	<li><span class="name">{translate text='First names'}:</span><span class="value">{$profile.firstname|escape}</span></li>
			<li><span class="name">{translate text='Last name'}:</span><span class="value">{$profile.lastname|escape}</span></li>
			<li><span class="name">{translate text='Address'} 1:</span><span class="value">{$profile.address1|escape}</span></li>
			<li><span class="name">{translate text='Address'} 2:</span><span class="value">{$profile.address2|escape}</span></li>
			<li><span class="name">{translate text='Postcode'}:</span><span class="value">{$profile.zip|escape}</span></li>
			<li><span class="name">{translate text='Phone number'}:</span><span class="value">{$profile.phone|escape}</span></li>
			<li><span class="name">{translate text='Borrower category'}:</span><span class="value">{$profile.group|escape}</span></li>

			<li><strong><span class="name">{translate text='Default pickup location'}:</span><span class="value">{if $profile.home_library}{foreach from=$pickup item=pickupLocation name="pickupLocationLoop"}{if $profile.home_library == $pickupLocation.locationID}{$pickupLocation.locationDisplay|translate}{/if}{/foreach}{else}{foreach from=$pickup item=pickupLocation name="pickupLocationLoop"}{if $defaultPickup == $pickupLocation.locationID}{$pickupLocation.locationDisplay|translate}{/if}{/foreach}{/if}</span></strong></li>
	    </ul>
	    <div class="bottom-row-holder">
	    	<div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Profile" class='more'>{translate text="Change default pickup location"}</a>
		    </div>
	    </div>
	</div>
</div>


<div id="sidebar">

	<div class="side-box">
	    <div class="heading">
	    	<h3 class="fines"><a href="{$path}/MyResearch/Fines">{translate text='Fines'}</a></h3>
	    </div>
	    {if $numberOfFines>0}
		    <div class="price-holder">
		    	<strong class="fines">{translate text='You have'} {$numberOfFines} {if $numberOfFines === 1}{translate text='fine totalling'}{else}{translate text='fines totalling'}{/if}:</strong>
	    		<strong class="price">£{$total/100|number_format:2}</strong>
		    </div>
		{else}
			<div class="price-holder">
				<strong class="fines">{translate text="You don’t have any fines"}:</strong>
				<strong class="price">£0.00</strong>
			</div>
		{/if}
	    <div class="bottom-row-holder">
		    <div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Fines" class="more">{translate text="more information"}</a>
		    </div>
		</div>
	</div>


	<div class="side-box">
	    <div class="heading">
	    	<h3 class="reservations"><a href="{$path}/MyResearch/Holds">{translate text='Reservations'}</a></h3>
	    </div>
	    {assign var=iteration value=0}
		{if is_array($callslipList) || is_array($availableList) || is_array($requestList)}
	   		
	   		<ul class="items">
		    
			    {if count($availableList)}
			    	{foreach from=$availableList item=available name="recordLoop"}
			    		{if $iteration++}{/if}
			    		<li class="available special green{if $iteration%2==0} color{/if} clearfix  {if $smarty.foreach.recordLoop.last && count($requestList)==0}last{/if}">
	    				    <img src="/interface/themes/kent/images/dashboard/ico-{$available.format.0|lower|regex_replace:"/[^a-z0-9]/":""}.png" width="16" height="16" alt="{translate text=$format}" class="png format" /> 
						    <div class="content">
	    				    	<a class="post-heading" href="{$url}/Record/{$available.id|escape:"url"}">{$available.title|escape}</a>
	    				    	<span class="available"><strong>{translate text="Available"}:</strong> {$available.location|escape}</span>
	    				    </div>
	    				</li>    
			    	{/foreach}
			    {/if}
			    
			    {if count($requestList)}
			    	{foreach from=$requestList item=available name="recordLoop"}
			    		{if $iteration++}{/if}
			    		<li class="{if $iteration%2==0}color{/if} clearfix">
	    				    <img src="/interface/themes/kent/images/dashboard/ico-{$available.format.0|lower|regex_replace:"/[^a-z0-9]/":""}.png" width="16" height="16" alt="{translate text=$format}" class="png format" /> 
						    <div class="content">
	    				    	<a class="post-heading" href="{$url}/Record/{$available.id|escape:"url"}">{$available.title|escape}</a>
								<span class="unavailable">{translate text="reservation_unavailable"}</span>
	    				    </div>
	    				</li>
			    	{/foreach}
			    	
			    {/if}
			
			</ul>
				
		{/if}
		
		{if (!count($callslipList) || !is_array($callslipList)) && (!count($availableList) || !is_array($availableList)) && (!count($requestList) || !is_array($requestList)) }
		    <div class="box-holder">
		    	<strong class="empty-text">
		    		{translate text='You do not have any holds or recalls placed'}.      
		    	</strong>
		    </div>
		{/if}
		
	    <div class="bottom-row-holder">
		    <div class="bottom-row clearfix">
		    	<a href="{$path}/MyResearch/Holds" class="more">{translate text="view all"}</a>
				{if $availableRequestLeft>=1}
				    <p>and {$availableRequestLeft} other{if $availableRequestLeft>1}s{/if}</p>
				{/if}
		    </div>
		</div>
	</div>
	
	<div class="side-box">
	    <div class="heading">
	    	<h3 class="favorites"><a href="{$path}/MyResearch/Favorites">{translate text="Favorites"}</a></h3>
	    </div>
	    {if count($listList)}
	    	<ul class="favorites-list">
		    	{foreach from=$listList item=list}
    	   			<li><a href="{$url}/MyResearch/MyList/{$list->id}">{$list->title|escape:"html"}</a> <span class="number">({$list->cnt})</span></li>
				{/foreach}
	    	</ul>
	    {else}
			<div class="box-holder">
				<strong class="empty-text">{translate text="You don’t have any favorites"}</strong>
				<p>{translate text="You can add resources to your favorites from the record view"}</p>
			</div>		
		{/if}
	    <div class="bottom-row-holder">
	    	<div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Favorites" class="more">{translate text="view all"}</a>
	    		{if $listListLeft}
	    			<p>and {$listListLeft} other{if $listListLeft>1}s{/if}</p>
	    		{/if}
	   		</div>
	   	</div>
	</div>
	<div class="side-box">
	    <div class="heading">
	    	<h3 class="tags"><a href="{$path}/MyResearch/Favorites">{translate text="Tags"}</a></h3>
	    </div>
	    
	    {if count($tagList)}
	    	<ul class="tags-list">
	    		{foreach from=$tagList item=tag}
     				<li><a href="{$url}/MyResearch/Favorites?tag[]={$tag->tag|escape:"url"}{foreach from=$tags item=mytag}&amp;tag[]={$mytag|escape:"url"}{/foreach}">{$tag->tag|escape:"html"}</a> <span class="number">({$tag->cnt})</span></li>
	       		{/foreach}
		    </ul>
	    {else}
			<div class="box-holder">
				<strong class="empty-text">{translate text="You don’t have any tags"}</strong>
				<p>{translate text="You can tag resources from the record view"}</p>
			</div>	
		{/if}
		
	    <div class="bottom-row-holder">
	    	<div class="bottom-row clearfix">
	    		<a href="{$path}/MyResearch/Favorites" class="more">{translate text="view all"}</a>
		    	{if $tagListLeft}
	    			<p>and {$tagListLeft} other{if $tagListLeft>1}s{/if}</p>
	    		{/if}
	    	</div>
	    </div>
	</div>
	
</div>

    <b class="bbot"><b></b></b>
    </div>
  </div>
</div>
