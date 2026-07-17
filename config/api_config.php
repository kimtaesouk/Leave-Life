<?php
// API 설정
// 카카오 REST API 키 (Kakao Developers에서 발급)
// https://developers.kakao.com/ 에서 애플리케이션을 등록하고 REST API 키를 발급받으세요
define('KAKAO_REST_API_KEY', '52210624108215bc63cfbb05fb65f4d5'); // 여기에 REST API 키를 입력하세요

// Geocoding API 사용 여부 (API 키가 없으면 false로 설정)
define('USE_GEOCODING', !empty(KAKAO_REST_API_KEY));

// Aligo SMS 설정 (https://www.aligo.in/)
define('ALIGO_API_KEY', ''); // API Key
define('ALIGO_USER_ID', ''); // 사용자 ID
define('ALIGO_SENDER', ''); // 발신번호
define('ALIGO_TESTMODE', false); // 테스트 모드 사용 여부
?>

