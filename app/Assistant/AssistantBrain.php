<?php

namespace App\Assistant;

/**
 * "عقل" المساعد: يأخذ الرسالة وسياقها ويرجع الرد.
 * أي سائق جديد (ذكاء اصطناعي محلي أو خارجي) يطبّق هذه الواجهة فقط،
 * ويُفعَّل من config/assistant.php دون تغيير الواجهات أو التطبيق.
 */
interface AssistantBrain
{
    public function reply(AssistantContext $context): AssistantReply;
}
