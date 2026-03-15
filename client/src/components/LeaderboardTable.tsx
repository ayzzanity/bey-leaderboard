import type { Player } from '../types/Player';

interface Props {
  players: Player[];
}

export default function LeaderboardTable({ players }: Props) {
  return (
    <table className="table">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Player</th>
          <th>Points</th>
          <th>Tournaments</th>
        </tr>
      </thead>

      <tbody>
        {players.map((player, index) => (
          <tr key={player.id}>
            <td>{index + 1}</td>
            <td>{player.name}</td>
            <td>{player.points}</td>
            <td>{player.tournaments}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
