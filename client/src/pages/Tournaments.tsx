import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { getTournaments } from '../api/api';
import type { Tournament } from '../types/Tournament';

export default function Tournaments() {
  const [tournaments, setTournaments] = useState<Tournament[]>([]);

  useEffect(() => {
    loadTournaments();
  }, []);

  const loadTournaments = async () => {
    const data = await getTournaments();
    setTournaments(data);
  };

  return (
    <div className="container">
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <h1>Tournaments</h1>

        <Link to="/admin/import">
          <button>Import Tournament</button>
        </Link>
      </div>

      {tournaments.length === 0 ? (
        <p>No tournaments imported yet.</p>
      ) : (
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
                  <Link to={`/tournaments/${t.id}`}>View</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
