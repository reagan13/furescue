<?php

/**
 * Longer lesson copy plus reading-comprehension quizzes drawn from that copy.
 *
 * @return array<string, array{body:string, quiz:list<array{q:string,options:list<string>,a:int}>}>
 */
function seed_elearning_lesson_catalog(): array
{
    return [
        'Dog Behavior Basics' => [
            'body' => <<<'HTML'
<p>Rescued dogs in Mati use body language before they use their voice. A freeze, whale-eye, or tight mouth means “give me space.” A loose wag and a play bow mean the dog is inviting contact.</p>
<p>Approach sideways, let the dog sniff the back of your hand, and never reach over the head. Markets and beaches are noisy — wait until the dog chooses to step closer.</p>
<p>If a street dog backs away, stop moving. Crouch, turn your shoulder, and toss food behind the dog instead of toward your feet. Direct stares make fear worse.</p>
<p>This lesson is for residents who meet aspins near the boulevard, wet market, or purok basketball court. When in doubt, call City Vet instead of forcing a rescue yourself.</p>
HTML,
            'quiz' => [
                ['q' => 'What should you do first when you meet a street dog?', 'options' => ['Reach over the head to pet it', 'Approach sideways and let it sniff', 'Stare until it looks away'], 'a' => 1],
                ['q' => 'Whale-eye and a freeze usually mean the dog wants…', 'options' => ['A long walk', 'Space', 'A loud greeting'], 'a' => 1],
                ['q' => 'If the dog backs away, what helps?', 'options' => ['Chase it into a corner', 'Crouch, turn your shoulder, and toss food behind it', 'Clap so it looks at you'], 'a' => 1],
            ],
        ],
        'Cat Care 101' => [
            'body' => <<<'HTML'
<p>Shelter cats settle faster when they have a hiding box, vertical space, and a predictable feeding time. A frightened puspin often flattens, hides, and refuses food; a sick cat may hide too, so watch appetite and litter use.</p>
<p>Leave the carrier open in a quiet room on the first day. Forcing contact teaches the cat that hands are traps. Sit nearby and let the cat come out when ready.</p>
<p>Keep water down at all times. Scoop the litter box daily. Sudden hiding plus no eating for more than a day is a reason to ask City Vet, not to wait another week.</p>
<p>Mati households with other pets should keep the newcomer in one room first. Rushing a resident cat often leads to urine marking and a returned adoption.</p>
HTML,
            'quiz' => [
                ['q' => 'What helps a shelter cat settle on day one?', 'options' => ['Forcing it out of the carrier', 'A hiding box and a predictable feeding time', 'A crowded living room'], 'a' => 1],
                ['q' => 'What should you do with the carrier at first?', 'options' => ['Leave it open in a quiet room', 'Put it away immediately', 'Shake it until the cat comes out'], 'a' => 0],
                ['q' => 'No eating plus hiding for more than a day means…', 'options' => ['The cat is just shy', 'Ask City Vet', 'Add more cats for company'], 'a' => 1],
            ],
        ],
        'Loose Leash Walking' => [
            'body' => <<<'HTML'
<p>A short, calm walk is better than a long, frantic one. Use a well-fitted flat collar or harness, reward slack leash, and stop when the dog pulls.</p>
<p>Practice in a quiet purok before trying Dahican Beach on a weekend. New rescuers often walk too far too soon; five good minutes beat thirty of pulling.</p>
<p>When the leash goes tight, become a tree. Wait for slack, then mark and treat. Do not yank. A front-clip harness can help large aspins without replacing training.</p>
<p>Keep the dog off the highway shoulder. If the dog is overwhelmed, turn around and go home. You can try the longer route another day.</p>
HTML,
            'quiz' => [
                ['q' => 'What should you do when the dog pulls?', 'options' => ['Speed up', 'Stop and wait for slack', 'Drop the leash'], 'a' => 1],
                ['q' => 'Where should you practice first?', 'options' => ['A quiet purok', 'Dahican Beach on a weekend', 'The highway'], 'a' => 0],
                ['q' => 'Which is better for a new walker?', 'options' => ['Thirty frantic minutes', 'Five calm minutes', 'No walks until the dog is perfect'], 'a' => 1],
            ],
        ],
        'Enrichment for Shelter Dogs' => [
            'body' => <<<'HTML'
<p>Kennel stress drops when dogs have a job. Kibble in a bottle, a soaked towel to shred, and five minutes of scent work are enough for most aspins.</p>
<p>Rotate toys so they stay interesting. Staff should log what each dog enjoys so adopters can continue it at home — a puzzle that worked in the kennel still works in a living room.</p>
<p>Food puzzles are not extra calories if you take that kibble out of the regular meal. Watch for toys that can be swallowed and throw them out.</p>
<p>Quiet enrichment matters as much as play. A chew after breakfast can prevent fence-running at noon heat.</p>
HTML,
            'quiz' => [
                ['q' => 'Why rotate toys?', 'options' => ['So they stay interesting', 'So dogs eat less', 'So staff can skip walks'], 'a' => 0],
                ['q' => 'What should staff log?', 'options' => ['Only bite incidents', 'What each dog enjoys', 'The weather'], 'a' => 1],
                ['q' => 'Food puzzles should…', 'options' => ['Be added on top of every meal', 'Use kibble from the regular meal', 'Replace water'], 'a' => 1],
            ],
        ],
        'Kitten Socialization' => [
            'body' => <<<'HTML'
<p>Between 2 and 9 weeks, kittens need gentle handling, household sounds, and positive food experiences. Keep litters together when possible.</p>
<p>Wash hands between rooms to limit ringworm spread in the holding area. A new litter should not share combs or blankets with a sick room.</p>
<p>Short sessions beat long ones. Touch paws, lift for a few seconds, then return the kitten to the pile with a treat. Stop if a kitten freezes or cries hard.</p>
<p>Unvaccinated kittens should not visit a crowded market. Household sounds — radio, tricycles outside, broom — can happen at home in short, calm bursts.</p>
HTML,
            'quiz' => [
                ['q' => 'The main socialization window for kittens is…', 'options' => ['2 to 9 weeks', '6 to 12 months', 'After one year'], 'a' => 0],
                ['q' => 'Why wash hands between rooms?', 'options' => ['To limit ringworm spread', 'To wake the kittens', 'It is optional'], 'a' => 0],
                ['q' => 'Where should an unvaccinated kitten not go?', 'options' => ['A quiet room at home', 'A crowded market', 'Beside its littermates'], 'a' => 1],
            ],
        ],
        'Emergency First Aid' => [
            'body' => <<<'HTML'
<p>Field checklist: scene safety first, then a muzzle if the animal may bite, then control bleeding with clean cloth. Keep the animal warm.</p>
<p>Call City Vet before moving a suspected fracture. Do not give human painkillers — many are toxic to dogs and cats.</p>
<p>If you must move the animal, use a rigid board or a towel sling and keep the spine as straight as you can. One person talks softly; the other lifts.</p>
<p>This module stays in draft until City Vet reviews the field steps. Use it as a reminder, not as a substitute for a veterinarian.</p>
HTML,
            'quiz' => [
                ['q' => 'What comes first at the scene?', 'options' => ['Scene safety', 'A photo for social media', 'Human painkillers'], 'a' => 0],
                ['q' => 'Before moving a suspected fracture you should…', 'options' => ['Pull the limb straight', 'Call City Vet', 'Give aspirin'], 'a' => 1],
                ['q' => 'Human painkillers are…', 'options' => ['Safe in small doses', 'Often toxic to dogs and cats', 'Required in the field'], 'a' => 1],
            ],
        ],
        'Senior Dog Care' => [
            'body' => <<<'HTML'
<p>Older aspins often need softer food, non-slip floors, and shorter walks. Watch for sudden thirst, cloudy eyes, or reluctance to rise.</p>
<p>Adoption of seniors is encouraged — they usually housetrain faster than puppies and already know house manners. A quiet corner beats a busy hallway.</p>
<p>Ramps help dogs who hesitate on steps. Raise the water bowl if the dog no longer crouches easily. Keep nails short so walks stay comfortable.</p>
<p>Sudden thirst or a dog that will not get up is a City Vet question, not something to wait out over a long weekend.</p>
HTML,
            'quiz' => [
                ['q' => 'What do many senior aspins need?', 'options' => ['Longer beach runs', 'Softer food and shorter walks', 'A second puppy for energy'], 'a' => 1],
                ['q' => 'Why are senior adoptions encouraged?', 'options' => ['They housetrain faster than puppies', 'They never need a vet', 'They stay puppies forever'], 'a' => 0],
                ['q' => 'Sudden thirst or not rising is a reason to…', 'options' => ['Wait a month', 'Ask City Vet', 'Add salt to food'], 'a' => 1],
            ],
        ],
        'Feral Cat TNR Guide' => [
            'body' => <<<'HTML'
<p>Trap-neuter-return keeps community cat numbers stable. Bait at dusk, cover the trap once caught, and return the cat to the same colony after recovery.</p>
<p>Coordinate with the barangay so residents do not relocate marked cats. A tipped ear means the cat was already sterilized — do not trap that cat again unless it is injured.</p>
<p>Use tuna or leftover rice and fish. Set the trap on level ground, check it often, and never leave a cat in the sun. Covering the trap lowers panic.</p>
<p>Relocating a colony to the beach or another purok usually fails. Cats try to go home, and the original block fills with new cats. Return to the same feeding site.</p>
HTML,
            'quiz' => [
                ['q' => 'When should you bait a TNR trap?', 'options' => ['At dusk', 'At high noon', 'Only at midnight'], 'a' => 0],
                ['q' => 'After the cat is caught you should…', 'options' => ['Leave the trap uncovered', 'Cover the trap', 'Move the colony to the beach'], 'a' => 1],
                ['q' => 'Where should a recovered cat go?', 'options' => ['Any quiet purok', 'The same colony', 'A different city'], 'a' => 1],
            ],
        ],
        'Puppy Socialization' => [
            'body' => <<<'HTML'
<p>The critical window closes around 16 weeks. Expose puppies to umbrellas, tricycles, and friendly vaccinated dogs in short sessions.</p>
<p>Never take an unvaccinated puppy to a crowded market. Home sounds and a quiet street are enough in the first weeks after a vaccine starts.</p>
<p>Pair each new thing with food. If the puppy freezes, increase distance and try a milder version. Flooding a fearful puppy makes the fear stick.</p>
<p>Keep sessions short. Three calm minutes with a parked tricycle beats a full hour at the boulevard on a festival day.</p>
HTML,
            'quiz' => [
                ['q' => 'The puppy socialization window closes around…', 'options' => ['16 weeks', 'Two years', 'Six months after adoption'], 'a' => 0],
                ['q' => 'An unvaccinated puppy should not go to…', 'options' => ['A quiet room', 'A crowded market', 'Its own yard'], 'a' => 1],
                ['q' => 'If the puppy freezes, you should…', 'options' => ['Push it closer', 'Increase distance and try a milder version', 'Yell so it looks up'], 'a' => 1],
            ],
        ],
        'Separation Anxiety' => [
            'body' => <<<'HTML'
<p>New adopters should practice short departures, a food puzzle at the door, and no dramatic goodbyes. Destruction and howling in the first week are common.</p>
<p>This module lists when to ask City Vet for a behavior referral: self-injury, all-day howling after two weeks, or a dog that will not eat when left alone.</p>
<p>Start with stepping outside for thirty seconds, then a minute, then five. Come back boring. A long first workday on day two often sets the panic.</p>
<p>Draft status means City Vet still reviews the referral steps. Use the short-departure practice now; wait for the published version before you treat it as policy.</p>
HTML,
            'quiz' => [
                ['q' => 'What should you practice with a new adopter dog?', 'options' => ['Dramatic goodbyes', 'Short departures and a food puzzle', 'Leaving it alone all day on day two'], 'a' => 1],
                ['q' => 'Howling in the first week is…', 'options' => ['Always an emergency', 'Common', 'Proof the dog was a bad match'], 'a' => 1],
                ['q' => 'A reason to ask City Vet for a behavior referral is…', 'options' => ['One bark at the gate', 'Self-injury or all-day howling after two weeks', 'Sleeping on the floor'], 'a' => 1],
            ],
        ],
        'Responsible Pet Ownership' => [
            'body' => <<<'HTML'
<p>In Mati, registered dogs should be vaccinated against rabies and kept from roaming highways. A leash on the boulevard is not optional when traffic is moving.</p>
<p>Pick up waste on the boulevard. If a pet is lost, report it in FurEscue instead of abandoning it. A found dog with a collar is easier to return than a dumped dog.</p>
<p>Keep cats indoors or in a screened area when possible. Roaming puspins are hit by tricycles and pick up parasites that spread in the purok.</p>
<p>Registration and rabies vaccination protect the household and the street. Ask City Vet about the current schedule rather than guessing dates from a neighbor.</p>
HTML,
            'quiz' => [
                ['q' => 'Registered dogs in Mati should be…', 'options' => ['Vaccinated against rabies', 'Left to roam the highway', 'Unregistered if they look healthy'], 'a' => 0],
                ['q' => 'If a pet is lost you should…', 'options' => ['Abandon it across town', 'Report it in FurEscue', 'Wait a year'], 'a' => 1],
                ['q' => 'Waste on the boulevard should be…', 'options' => ['Left for rain', 'Picked up', 'Kicked into the gutter'], 'a' => 1],
            ],
        ],
        'Reading a Health Record' => [
            'body' => <<<'HTML'
<p>Adopters should understand vaccine dates, next-due windows, deworming status, and why a “not healthy” flag blocks listing.</p>
<p>Bring questions to the meet-and-greet instead of guessing dosages at home. A health record is a log, not a prescription you refill on your own.</p>
<p>Look for the last rabies date and the next-due window. If a listing is blocked, the animal still needs a vaccination row and a vital on file — that is the health-ready gate.</p>
<p>Ask who the attending vet was and what “partial” vaccination means for that animal. Do not skip the meet-and-greet just because the card looks complete.</p>
HTML,
            'quiz' => [
                ['q' => 'A “not healthy” flag…', 'options' => ['Blocks listing', 'Means the animal cannot be fed', 'Is only for cats'], 'a' => 0],
                ['q' => 'Where should dosage questions be asked?', 'options' => ['At the meet-and-greet', 'From a random Facebook group only', 'Never'], 'a' => 0],
                ['q' => 'The health-ready gate needs…', 'options' => ['A cute photo only', 'A vaccination row and a vital', 'A 3D model'], 'a' => 1],
            ],
        ],
        'Home Check Preparation' => [
            'body' => <<<'HTML'
<p>Before applying, photograph the yard fence, indoor sleeping area, and food storage. Note other pets and children in the household.</p>
<p>A failed home check is usually about escape risk, not about how much you love animals. Gaps under a gate or a broken window latch matter more than a new bed.</p>
<p>Put food in a closed bin. Show where the animal will sleep at night. If you rent, have the landlord’s written OK ready.</p>
<p>City Vet is not grading your furniture. They are checking whether the animal can get out onto the highway or fight with an unmentioned resident dog.</p>
HTML,
            'quiz' => [
                ['q' => 'What should you photograph before applying?', 'options' => ['Only the animal', 'Yard fence, sleeping area, and food storage', 'Your favorite park'], 'a' => 1],
                ['q' => 'Home checks usually fail because of…', 'options' => ['Escape risk', 'The color of the walls', 'Too much love'], 'a' => 0],
                ['q' => 'If you rent, bring…', 'options' => ['Nothing extra', 'The landlord’s written OK', 'A second animal as proof'], 'a' => 1],
            ],
        ],
        'Handling Fearful Dogs' => [
            'body' => <<<'HTML'
<p>Crouch sideways, toss food behind the dog, and wait. Tight spaces and direct stares make fear worse.</p>
<p>Rescuers should carry a slip lead and a towel; residents should wait for trained staff. A scared aspin in an alley is not a DIY catch.</p>
<p>Talk low. Do not loom. If the dog takes food and retreats, that is progress — do not follow it under a house.</p>
<p>If traffic is close, block the road first and call for help. Saving a dog by chasing it into a tricycle lane is not a rescue.</p>
HTML,
            'quiz' => [
                ['q' => 'How should you start with a fearful dog?', 'options' => ['Crouch sideways and toss food behind it', 'Corner it and stare', 'Grab the collar immediately'], 'a' => 0],
                ['q' => 'Residents who see a scared dog in an alley should…', 'options' => ['Wait for trained staff', 'Crawl under the house after it', 'Honk to move it'], 'a' => 0],
                ['q' => 'What makes fear worse?', 'options' => ['Soft talk', 'Tight spaces and direct stares', 'Waiting'], 'a' => 1],
            ],
        ],
        'Introducing a New Cat' => [
            'body' => <<<'HTML'
<p>Keep the newcomer in one room for several days. Swap bedding, feed on opposite sides of the door, then allow supervised visits.</p>
<p>Rushing a resident cat often leads to urine marking and returned adoptions. Slow is not unkind — it is how cats decide the house is still theirs.</p>
<p>If hissing happens, go back a step. Do not punish either cat. Use two litter boxes and two food stations from day one.</p>
<p>Only open the door for a full visit when both cats eat calmly on their own side. That may take a week. That is normal.</p>
HTML,
            'quiz' => [
                ['q' => 'Where should a new cat stay at first?', 'options' => ['The whole house', 'One room for several days', 'Outside'], 'a' => 1],
                ['q' => 'Rushing the introduction often leads to…', 'options' => ['Faster friendship', 'Urine marking and returned adoptions', 'Better appetite'], 'a' => 1],
                ['q' => 'If the cats hiss, you should…', 'options' => ['Go back a step', 'Force them to share a bed', 'Remove all litter boxes'], 'a' => 0],
            ],
        ],
        'Heatstroke and Rainy Season Care' => [
            'body' => <<<'HTML'
<p>Pujada Bay weather swings from hot noon sun to long rains. Never leave a dog in a tricycle cab. Dry ears after rain to reduce infection, and watch panting that does not stop in the shade.</p>
<p>Offer water before a walk, not only after. Walk early or late. A black aspin on asphalt at noon overheats faster than you think.</p>
<p>After rain, towel the belly and paws. Damp bedding grows skin problems. Move the house to a dry corner if the floor floods.</p>
<p>Panting that does not stop in the shade, bright-red gums, or collapse is an emergency — go to City Vet. Do not give ice water down the throat.</p>
HTML,
            'quiz' => [
                ['q' => 'A dog should never be left in…', 'options' => ['A shaded yard with water', 'A tricycle cab', 'A tiled indoor room'], 'a' => 1],
                ['q' => 'After rain you should…', 'options' => ['Dry the ears and towel the belly', 'Leave the dog wet overnight', 'Skip water'], 'a' => 0],
                ['q' => 'Panting that does not stop in the shade is…', 'options' => ['Normal beach weather', 'An emergency for City Vet', 'A reason to run farther'], 'a' => 1],
            ],
        ],
    ];
}
