<?php

namespace App\Constants;

class Status
{

    const ENABLE = 1;
    const DISABLE = 0;

    const YES = 1;
    const NO = 0;

    const VERIFIED = 1;
    const UNVERIFIED = 0;

    const PAYMENT_INITIATE = 0;
    const PAYMENT_SUCCESS = 1;
    const PAYMENT_PENDING = 2;
    const PAYMENT_REJECT = 3;

    const TICKET_OPEN = 0;
    const TICKET_ANSWER = 1;
    const TICKET_REPLY = 2;
    const TICKET_CLOSE = 3;

    const PRIORITY_LOW = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_HIGH = 3;

    const USER_ACTIVE = 1;
    const USER_BAN = 0;

    const KYC_UNVERIFIED = 0;
    const KYC_PENDING = 2;
    const KYC_VERIFIED = 1;

    const GOOGLE_PAY = 5001;

    const CUR_BOTH = 1;
    const CUR_TEXT = 2;
    const CUR_SYM = 3;

    const FIRST = 1;

    const VIDEO = 1;
    const ARTICLE = 2;


    CONST LECTURE = 1;
    const QUIZ = 2;
   

    CONST PERCENT= 1;
    CONST FIXED = 2;

    CONST PENDING = 2;
    CONST REVIEW = 3;
    CONST REJECT = 4;
    CONST APPROVED = 1;

    const DISCOUNT_FIXED = 1;
    const DISCOUNT_PERCENT = 2;

    const BEGINNER = 1;  
    const INTERMEDIATE = 2;
    const ADVANCED = 3;

    
}
