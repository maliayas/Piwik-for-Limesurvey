<?php

class PiwikPlugin extends PluginBase {
	protected $storage = 'DbStorage';
	static protected $description = 'Adds Piwik tracking codes to Limesurvey pages.';
	static protected $name = 'Piwik for Limesurvey';

	protected $settings = array(
		'piwik_trackAdminPages' => array(
			'type'=>'select',
			'options'=>array(0=>'No',1=>'Yes'),
			'label'=>'Track Admin pages',
			'default'=> 1
		),
		'piwik_trackSurveyPages' => array(
			'type'=>'select',
			'options'=>array(0=>'No',1=>'Yes'),
// 			'label'=>'Track Survey pages by default <br><small>(Survey admins can override this)</small>', //Once we can track individual surveys, say something like this.
			'label'=>'Track Survey pages ', //For the moment, just track or don't track. 
			'default'=> 1
		),
		'piwik_piwikURL'=>array(
			'type'=>'string',
			'label'=>'URL to Piwik\'s directory <br/><small>(Can be relative or absolute)</small>',
			'default'=>'/piwik/'
		),
		'piwik_siteID'=>array(
			'type'=>'string',
			'label'=>"Piwik SiteId  <br/>(<small><a href='http://piwik.org/faq/general/faq_19212/' target='_new'>What should you put here?</a></small>)",
			'default'=>1
		),
/* Coming soon: Enable or diable admins from changing settings.
		'piwik_suveyAdminCanChangeSettings' => array(
			'type'=>'select',
			'options'=>array(1=>'Yes',0=>'No'),
			'label'=>'Allow Survey Administrators to change these settings for their surveys',
			'default'=> 1
		),
*/
	);

	public function __construct(PluginManager $manager, $id)
	{
		parent::__construct($manager, $id);
		$this->subscribe('newSurveySettings');
		$this->subscribe('afterPluginLoad');
		$this->subscribe('beforeSurveyPage');
		$this->subscribe('beforeSurveySettings');
	}

	public function afterPluginLoad(){
		/*
		There's no clean way to detect whether this is a survey or admin page, so this workaround allows admins to choose whether surveys and/or admin interfaces are tracked.
		Logic: If admin pages are being tracked and the tracking code hasn't been registered, then the code will be added (even to survey pages). If admin pages are not being tracked, then no code is added  (and we'll have to add tracking code to survey pages later). Yuck.
		*/
		
		if (($this->registeredTrackingCode!=true)&&($this->get('piwik_trackAdminPages', null, null, false))){ 
			$this->loadPiwikTrackingCode();

		}
	}

	public function beforeSurveyPage(){
		//Load tracking code on a survey page, or unload it if necessary.
		//TODO: Enable this per-survey. This requires us to be able to retrieve per-survey settings!
		

        $event = $this->getEvent();
		$trackThisSurvey=$this->get("surveysettings.{$this->id}");
		if ($trackThisSurvey==NULL) { $trackThisSurvey=$this->get('piwik_trackSurveyPages', null, null, false); } //Use default setting if the survey has not set their preference.
//		App()->getClientScript()->registerScript('piwikPlugin_track',"console.log('Tracking Status".(int)$trackThisSurvey."');"); //debug message... seems to always be null??

		if ($trackThisSurvey==true){
			$this->loadPiwikTrackingCode();
		} elseif ($this->registeredTrackingCode==true){ //Otherwise, Unload the tracking code if it's been loaded.
				$this->unloadPiwikTrackingCode();
		}
	}
	
	
	

	function unloadPiwikTrackingCode(){
			App()->getClientScript()->registerScript('piwikPlugin_TrackingCode','',CClientScript::POS_END);
			$this->registeredTrackingCode=false; //false
	}


	function loadPiwikTrackingCode(){
		$piwikID=trim($this->get('piwik_siteID', null, null, false));
		$piwikURL=trim($this->get('piwik_piwikURL', null, null, false));

		//For comment: Could check if the last character is a slash to ensure it's a directory.
		if (substr($piwikURL,-1)<>"/"){ $piwikURL=""; }

		//Some basic error checking...
		if (($piwikID=="")||($piwikURL=="")){
			App()->getClientScript()->registerScript('piwikPlugin_TrackingCodeError',"console.log('Piwik plugin has not been correctly set up. Please check the settings.');");
		} else {
			//Generate the code..
			$baseTrackingCode="var _paq = _paq || [];
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u='".$piwikURL."';
    _paq.push(['setTrackerUrl', u+'piwik.php']);
    _paq.push(['setSiteId', ".$piwikID."]);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
  })();";
			App()->getClientScript()->registerScript('piwikPlugin_TrackingCode',$baseTrackingCode,CClientScript::POS_END);
			$this->registeredTrackingCode=true; //Prevents loading the code twice through afterPluginLoad() AND beforeSurveyPage()
		}
	}



        public function beforeSurveySettings()
        {
/*		Commented out until we can retrieve the settings back again in beforeSurveyPage().
            $event = $this->getEvent();
            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => array(
                    'piwik_trackThisSurvey' => array(
                        'type' => 'select',
                        'options'=>array(0=>'No',
                            1=>'Yes'),
                        'default'=>$this->get('piwik_trackSurveyPages', null, null, false), //Default is whatever is set by the superadmin.
                        'label' => 'Collect web analytics data from respondents',
                        'current' => $this->get('piwik_trackThisSurvey', 'Survey', $event->get('survey'))
                    )
                )
            ));
*/
        }

        public function newSurveySettings()
        { //28/Jan/2015: To be honest,  I have NO idea what this function does. It's not documented anywhere, but after a lot of trial and error I discovered it _IS NECESSARY_ for any per-survey settings to actually hold. Todo: Perhaps this should be integrated into the core?
            $event = $this->getEvent();
            foreach ($event->get('settings') as $name => $value)
            {
                $this->set($name, $value, 'Survey', $event->get('survey'));
            }
        }


	/* ----------Possible upcoming feature: Piwik Event tracking.---------
	public function afterSurveyComplete($surveyID,$responseID){
		//Add a Piwik event signifying the end of the survey
		$eventJS="_paq.push(['trackEvent','LimeSurvey surveys', 'Survey #".surveyID."', 'Submitted']);";
		App()->getClientScript()->registerScript('piwikPlugin_afterSurveyComplete',$eventJS);
	}
*/

	/* ----------Possible upcoming feature: Piwik Content tracking.---------
	function beforeSurveyPage(){
		//Track the display of specific questions using Piwik's content tracking interface.
		return false;
	}
*/


}