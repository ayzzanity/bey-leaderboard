import type { Standing } from '../types/Standing';

interface Props {
  standings: Standing[];
}

export default function StandingsTable({ standings }: Props) {
  console.log('StandingsTable props:', standings);
  return (
    <table className="table">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Player</th>
          <th>Wins</th>
          <th>Losses</th>
          {/* <th>Points</th> */}
        </tr>
      </thead>

      <tbody>
        {standings
          .sort((a, b) => a.swiss_rank - b.swiss_rank)
          .map((s) => (
            <tr key={s.player_id}>
              <td>{s.swiss_rank}</td>
              <td>{s.player.name}</td>
              <td>{s.swiss_wins}</td>
              <td>{s.swiss_losses}</td>
              {/* <td>{s.points}</td> */}
            </tr>
          ))}
      </tbody>
    </table>
  );
}
