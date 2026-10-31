<?php

namespace Database\Seeders;

use App\Models\TriviaQuestion;
use Illuminate\Database\Seeder;

/**
 * Bilingual trivia bank — beginner-friendly cricket knowledge.
 * Idempotent: keyed on the English question text.
 */
class TriviaSeeder extends Seeder
{
    private const BANK = [
        [
            'en' => 'How many players are on the field for the bowling team?',
            'pt' => 'Quantos jogadores ficam em campo no time que lança?',
            'opts' => [['9', '10', '11', '12'], ['9', '10', '11', '12']],
            'answer' => 2, 'd' => 'easy',
        ],
        [
            'en' => 'How many runs is a ball hit over the boundary on the full worth?',
            'pt' => 'Quantas corridas vale uma bola rebatida por cima do limite sem quicar?',
            'opts' => [['4', '6', '2', '1'], ['4', '6', '2', '1']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'What is it called when a bowler bowls six legal balls in a row?',
            'pt' => 'Como se chama quando o lançador faz seis bolas legais seguidas?',
            'opts' => [['A set', 'An over', 'A spell', 'An innings'], ['Um set', 'Um over', 'Um spell', 'Uma entrada']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'A batter is out "LBW" when the ball hits their…',
            'pt' => 'Um rebatedor sai por "LBW" quando a bola atinge…',
            'opts' => [['Bat first', 'Helmet', 'Leg in front of the stumps', 'Hands'], ['O taco primeiro', 'O capacete', 'A perna na frente dos stumps', 'As mãos']],
            'answer' => 2, 'd' => 'medium',
        ],
        [
            'en' => 'What does a "wide" ball add to the score?',
            'pt' => 'O que uma bola "wide" adiciona ao placar?',
            'opts' => [['Nothing', '1 run to the batting team', '1 run to the batter', 'A wicket'], ['Nada', '1 corrida ao time que rebate', '1 corrida ao rebatedor', 'Um wicket']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'How many stumps are there at each end of the pitch?',
            'pt' => 'Quantos stumps existem em cada ponta do pitch?',
            'opts' => [['2', '3', '4', '5'], ['2', '3', '4', '5']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'Which dismissal does NOT credit the bowler with a wicket?',
            'pt' => 'Qual eliminação NÃO dá wicket ao lançador?',
            'opts' => [['Bowled', 'Caught', 'Run out', 'LBW'], ['Bowled', 'Caught', 'Run out', 'LBW']],
            'answer' => 2, 'd' => 'medium',
        ],
        [
            'en' => 'What is a "duck" in cricket?',
            'pt' => 'O que é um "duck" no críquete?',
            'opts' => [['Scoring 0 runs', 'Scoring 100 runs', 'A fielding error', 'A slow pitch'], ['Marcar 0 corridas', 'Marcar 100 corridas', 'Um erro de defesa', 'Um pitch lento']],
            'answer' => 0, 'd' => 'easy',
        ],
        [
            'en' => 'In a T20 match, how many overs does each team bat?',
            'pt' => 'Numa partida T20, quantos overs cada time rebate?',
            'opts' => [['10', '20', '40', '50'], ['10', '20', '40', '50']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'What is "DRS" used for?',
            'pt' => 'Para que serve o "DRS"?',
            'opts' => [['Reviewing umpire decisions', 'Measuring pitch speed', 'Team substitutions', 'Rain delays'], ['Revisar decisões do árbitro', 'Medir velocidade do pitch', 'Substituições de times', 'Atrasos por chuva']],
            'answer' => 0, 'd' => 'medium',
        ],
        [
            'en' => 'Which country won the first-ever Cricket World Cup (1975)?',
            'pt' => 'Qual país venceu a primeira Copa do Mundo de críquete (1975)?',
            'opts' => [['Australia', 'England', 'West Indies', 'India'], ['Austrália', 'Inglaterra', 'Índias Ocidentais', 'Índia']],
            'answer' => 2, 'd' => 'hard',
        ],
        [
            'en' => 'The area between the two sets of stumps is called the…',
            'pt' => 'A área entre os dois conjuntos de stumps chama-se…',
            'opts' => [['Crease', 'Pitch', 'Boundary', 'Square'], ['Crease', 'Pitch', 'Boundary', 'Square']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'A "maiden over" is an over with…',
            'pt' => 'Um "maiden over" é um over com…',
            'opts' => [['Only wides', 'No wickets', 'No runs scored', 'Six sixes'], ['Só wides', 'Sem wickets', 'Nenhuma corrida', 'Seis sixes']],
            'answer' => 2, 'd' => 'medium',
        ],
        [
            'en' => 'How is a "no ball" most commonly caused?',
            'pt' => 'Qual é a causa mais comum de um "no ball"?',
            'opts' => [['Overstepping the crease', 'Bowling too fast', 'Wearing the wrong shoes', 'Wet ball'], ['Pisar além do crease', 'Lançar rápido demais', 'Chuteira errada', 'Bola molhada']],
            'answer' => 0, 'd' => 'medium',
        ],
        [
            'en' => 'What equipment does the wicketkeeper wear that fielders do not?',
            'pt' => 'Que equipamento o wicketkeeper usa que os fielders não usam?',
            'opts' => [['Helmet only', 'Gloves and pads', 'Spiked shoes', 'Elbow guard'], ['Só capacete', 'Luvas e caneleiras', 'Chuteira com cravos', 'Cotoveleira']],
            'answer' => 1, 'd' => 'easy',
        ],
        [
            'en' => 'In a tied match both teams score the same runs. What usually decides it?',
            'pt' => 'Numa partida empatada, o que geralmente decide o vencedor?',
            'opts' => [['Toss again', 'Super Over', 'First innings winner', 'Fewer players used'], ['Novo sorteio', 'Super Over', 'Vencedor da 1ª entrada', 'Menos jogadores usados']],
            'answer' => 1, 'd' => 'medium',
        ],
        [
            'en' => 'Where is cricket most popular in South America?',
            'pt' => 'Onde o críquete é mais popular na América do Sul?',
            'opts' => [['Argentina', 'Brazil', 'Chile', 'Guyana region'], ['Argentina', 'Brasil', 'Chile', 'Região da Guiana']],
            'answer' => 3, 'd' => 'hard',
        ],
        [
            'en' => 'A batter scores a "century" when they reach…',
            'pt' => 'Um rebatedor faz um "century" ao chegar a…',
            'opts' => [['50 runs', '75 runs', '100 runs', '200 runs'], ['50 corridas', '75 corridas', '100 corridas', '200 corridas']],
            'answer' => 2, 'd' => 'easy',
        ],
        [
            'en' => 'What is the maximum number of overs one bowler can bowl in a T20?',
            'pt' => 'Qual o máximo de overs que um lançador pode fazer num T20?',
            'opts' => [['4', '5', '6', '10'], ['4', '5', '6', '10']],
            'answer' => 0, 'd' => 'medium',
        ],
        [
            'en' => 'The fielding position directly behind the wicketkeeper is…',
            'pt' => 'A posição de campo diretamente atrás do wicketkeeper é…',
            'opts' => [['Slip', 'Point', 'Mid-off', 'Fine leg'], ['Slip', 'Point', 'Mid-off', 'Fine leg']],
            'answer' => 0, 'd' => 'hard',
        ],
    ];

    public function run(): void
    {
        foreach (self::BANK as $q) {
            TriviaQuestion::firstOrCreate(
                ['question->en' => $q['en']],
                [
                    'question' => ['en' => $q['en'], 'pt-BR' => $q['pt']],
                    'options' => ['en' => $q['opts'][0], 'pt-BR' => $q['opts'][1]],
                    'answer_index' => $q['answer'],
                    'difficulty' => $q['d'],
                ],
            );
        }
    }
}
