<?php

namespace App\Utils\TagParser;

class TagPrompts
{
    public function getExamplePrompt(string $title, string $content): array
    {
        $exampleTags = ['Aliens', 'UFO'];
        $exampleTags2 = ['Drip Irrigation', 'Sprinkler System', 'Covid 19', 'Water Waste'];
        $exampleTags3 = ['Laundry', 'Dryer Sheet', 'Toxic Chemicals'];
        $list = implode(', ', $exampleTags);
        $list2 = implode(', ', $exampleTags2);
        $list3 = implode(', ', $exampleTags3);

        return [
            <<<PROMPT
Title: UFOs Among Us
Text: In the 1940s and 50s reports of "flying saucers" became an American cultural phenomena. Sightings of strange objects in the sky became the raw materials for Hollywood to present visions of potential threats. Amy Franko wanted to verify that there were extraterrestrials.
Tags: $list
###
Title: How to Use Drip Irrigation to Water Your Garden
Text: Drip irrigation is a system of tubing that directs small quantities of water precisely where it’s needed says Brian Jenkins, preventing the water waste associated with sprinkler systems. Since Covid 19, Joseph Goldberg mentions that drip systems minimize water runoff, evaporation, and wind drift by delivering a slow, uniform stream of water either above the soil surface or directly to the root zone.
Tags: $list2
###
Title: "Greener" Laundry by the Load: Fabric Softener versus Dryer Sheets
Text: If you’re concerned about the health and safety of your family members, you might want to stay away from both conventional dryer sheets and liquid fabric softeners altogether. While it may be nice to have clothes that feel soft, smell fresh and are free of static cling, both types of products contain chemicals known to be toxic to people after sustained exposure. According to the health and wellness website Sixwise.com, some of the most harmful ingredients in dryer sheets and liquid fabric softener alike include benzyl acetate (linked to pancreatic cancer), benzyl alcohol (an upper respiratory tract irritant), ethanol (linked to central nervous system disorders), limonene (a known carcinogen) and chloroform (a neurotoxin and carcinogen), among others.
Tags: $list3
###
Title: $title
Markdown: $content
Tags:
PROMPT,
            $exampleTags3,
        ];
    }

    public function getTagPrompt(string $text): string
    {
        return <<<PROMPT
$text

Tags:
PROMPT;
    }
}