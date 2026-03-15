import { Link } from 'react-router-dom';
import type { Tournament } from '../types/Tournament';

interface Props {
  tournaments: Tournament[];
}

export default function TournamentList({ tournaments }: Props) {
  return (
    <table className="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Date</th>
          <th>Players</th>
          <th></th>
        </tr>
      </thead>

      <tbody>
        {tournaments.map((t) => (
          <tr key={t.id}>
            <td>{t.name}</td>
            <td>{t.date}</td>
            <td>{t.participants_count}</td>
            <td>
              <Link to={`/standings/${t.id}`}>View</Link>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
